<?php
/** Offline payment regression suite. Run: php tests/payments/v21.php
 * Requires PHP 8.2+, pdo_sqlite, mbstring. Uses only in-memory test data;
 * never boots the production app, reads .env or contacts a bank.
 */
declare(strict_types=1);
$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});
require $root . '/app/Support/helpers.php';
use App\Core\{Database, Config, Logger, RateLimiter, Request, View};
use App\Payments\{PaymentGatewayManager, PaymentStatusMapper, PaymentStateToken, PaymentLock};
use App\Payments\Http\{HttpClientInterface, HttpResult};
use App\Repositories\{DonationRepository, PaymentRepository, FundraiserRepository, DonorRepository};
use App\Services\{DonationService, PaymentStatusService, SettingsService, AuditService, OrderNumberService};
use App\Http\Controllers\Public\PaymentController;
use App\Mail\Mailer;

final class V21Http implements HttpClientInterface {
    public array $queue = [];
    public array $calls = [];
    public function reply(array $body, int $code = 200): void { $this->queue[] = new HttpResult($code, json_encode($body, JSON_THROW_ON_ERROR)); }
    private function take(string $url, array $data): HttpResult {
        $this->calls[] = [$url, $data];
        if (!$this->queue) { throw new RuntimeException('Unexpected bank call'); }
        return array_shift($this->queue);
    }
    public function get(string $url, array $query = [], array $options = []): HttpResult { return $this->take($url, $query); }
    public function postJson(string $url, array $payload, array $options = []): HttpResult { return $this->take($url, $payload); }
    public function postForm(string $url, array $fields, array $options = []): HttpResult { return $this->take($url, $fields); }
}
$count = 0;
function check(bool $ok, string $label): void {
    global $count;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    ++$count; echo "PASS: $label\n";
}
Config::set('app.key', 'offline-regression-key-not-a-live-secret');
Config::set('app.url', 'https://donations.example.test');
$db = new Database(['driver' => 'sqlite', 'database' => ':memory:']);
$db->pdo()->sqliteCreateFunction('UTC_TIMESTAMP', static fn () => gmdate('Y-m-d H:i:s'));
// Minimal isolated schema. Production schema/migrations are never changed.
$db->pdo()->exec(<<<SQL
CREATE TABLE gateways (id INTEGER PRIMARY KEY, code TEXT UNIQUE, name TEXT);
INSERT INTO gateways VALUES (1,'meezan','Meezan Bank'),(2,'etisalat','UBL');
CREATE TABLE donors (id INTEGER PRIMARY KEY, name TEXT, email TEXT, phone TEXT);
CREATE TABLE fundraisers (id INTEGER PRIMARY KEY, title TEXT, slug TEXT);
CREATE TABLE donations (id INTEGER PRIMARY KEY, public_reference TEXT UNIQUE, status TEXT, gateway_id INTEGER,
 amount_minor INTEGER, currency TEXT, completed_at TEXT, donor_id INTEGER, fundraiser_id INTEGER, receipt_email_status TEXT);
CREATE TABLE payment_locks (lock_key TEXT PRIMARY KEY, owner_token TEXT, expires_at TEXT);
CREATE TABLE payment_callbacks (id INTEGER PRIMARY KEY, payment_transaction_id INTEGER, gateway_id INTEGER,
 callback_method TEXT, raw_safe_payload_json TEXT, received_transaction_id TEXT, validation_result TEXT, reason TEXT, processed_at TEXT);
CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, entity_type TEXT, entity_id TEXT,
 safe_metadata_json TEXT, ip_address TEXT, user_agent TEXT);
CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT);
SQL);
$sql = file_get_contents($root . '/database/migrations/0004_payments.sql');
preg_match('/CREATE TABLE IF NOT EXISTS `payment_transactions` \((.*?)\n\)/s', $sql, $table);
preg_match_all('/^  `([^`]+)`/m', $table[1], $columns);
$defs = [];
foreach ($columns[1] as $col) { $defs[] = "`$col` " . ($col === 'id' ? 'INTEGER PRIMARY KEY' : 'TEXT'); }
$db->pdo()->exec('CREATE TABLE payment_transactions (' . implode(',', $defs)
    . ', UNIQUE(merchant_order_id), UNIQUE(idempotency_key), UNIQUE(gateway_id,provider_transaction_id))');
$logger = new Logger(sys_get_temp_dir() . '/almarah-v21-test-logs');
$http = new V21Http();
$defaults = ['gateways' => [
 'meezan' => ['api_url' => 'https://bank.example.test/rest', 'username' => 'test', 'password' => 'test',
    'currency_code' => '586', 'currency_name' => 'PKR', 'allowed_hosts' => ['bank.example.test']],
 'etisalat' => ['sandbox_url' => 'https://bank.example.test', 'live_url' => 'https://bank.example.test',
    'username' => 'test', 'password' => 'test', 'customer' => 'test', 'currency' => 'PKR', 'allowed_hosts' => ['bank.example.test']],
]];
$manager = new PaymentGatewayManager(new SettingsService($db, $logger, $defaults), $db, $logger, new PaymentStatusMapper(), 'https://donations.example.test', $http);
$donations = new DonationRepository($db); $payments = new PaymentRepository($db);
$audit = new AuditService($db, $logger);
// No donor email or fundraiser owner in fixtures, so notification services are not invoked.
$mailer = (new ReflectionClass(Mailer::class))->newInstanceWithoutConstructor();
$status = new PaymentStatusService($donations, $payments, new FundraiserRepository($db), new PaymentStatusMapper(), $mailer, $audit, $logger);
$lock = new PaymentLock($db);
$service = new DonationService($db, $donations, $payments, new DonorRepository($db), $manager, $status, $lock, new RateLimiter($db), $audit, $logger, new OrderNumberService($db));
$controller = (new ReflectionClass(PaymentController::class))->newInstanceWithoutConstructor();
foreach (['donations' => $service, 'payments' => $payments, 'logger' => $logger] as $prop => $value) {
    (new ReflectionProperty(PaymentController::class, $prop))->setValue($controller, $value);
}
function fixture(string $gateway, bool $expired = false): array {
    global $db, $payments, $fixtureCount;
    static $n = 0; ++$n; $fixtureCount = $n;
    $order = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    $state = PaymentStateToken::issue($order);
    $gid = $gateway === 'meezan' ? 1 : 2;
    $did = $db->insert('donations', ['public_reference' => 'TEST-' . $n, 'status' => 'processing',
        'gateway_id' => $gid, 'amount_minor' => 1050, 'currency' => 'PKR']);
    $id = $payments->createTransaction(['donation_id' => $did, 'gateway_id' => $gid, 'environment' => 'sandbox',
        'merchant_order_id' => $order, 'provider_order_id' => $gateway === 'meezan' ? 'bank-order-' . $n : $order,
        'provider_transaction_id' => $gateway === 'etisalat' ? 'bank-txn-' . $n : null,
        'callback_state_hash' => hash('sha256', $state), 'callback_state_expires_at' => gmdate('Y-m-d H:i:s', time() + ($expired ? -3600 : 3600)),
        'amount_minor' => 1050, 'currency' => 'PKR', 'status' => 'processing', 'request_reference' => 'TEST-' . $n, 'idempotency_key' => 'test-' . $n]);
    return [$payments->find($id), $state];
}
function bodyFor(string $gateway, array $tx, string $status = 'completed'): array {
    if ($gateway === 'meezan') { return ['OrderStatus' => match ($status) {'completed'=>2, 'failed'=>6, default=>1},
        'Amount' => '1050.00', 'Currency' => '586', 'ErrorCode' => '0', 'ErrorMessage' => $status === 'failed' ? 'Card declined' : '', 'orderId' => $tx['provider_order_id']]; }
    return ['Transaction' => ['ResponseCode' => match ($status) {'completed'=>'0', 'failed'=>'51', default=>'210'},
        'ResponseDescription' => $status === 'failed' ? 'Card declined' : $status, 'TransactionID' => $tx['provider_transaction_id'],
        'ApprovalCode' => 'ABC123', 'OrderID' => $tx['merchant_order_id'], 'Amount' => '10.500', 'Currency' => 'PKR']];
}
function payloadFor(string $gateway, array $tx): array { return $gateway === 'meezan' ? ['orderId'=>$tx['provider_order_id']] : ['OrderID'=>$tx['merchant_order_id'],'TransactionID'=>$tx['provider_transaction_id']]; }
function runReturn(string $gateway, array $payload, string $method = 'POST'): string {
    global $controller;
    $request = new Request($method, '/payments/' . $gateway . '/return', $method === 'GET' ? $payload : [], $method === 'POST' ? $payload : [], [], []);
    $response = $gateway === 'meezan' ? $controller->meezanReturn($request) : $controller->etisalatReturn($request);
    return $response->headers()['Location'];
}
function expectStatuses(array $tx, string $expected, string $label): void {
    global $payments, $donations;
    check($payments->find((int)$tx['id'])['status'] === $expected && $donations->find((int)$tx['donation_id'])['status'] === $expected, $label);
}
// Registration/handoff compatibility checks catch accidental changes outside verification.
foreach (['meezan','etisalat'] as $gw) {
    $gateway = $manager->gateway($gw);
    $token = PaymentStateToken::issue('9999');
    $returnUrl = $gateway->returnUrl($token, '9999');
    $request = new \App\Payments\Dtos\PaymentRequest('9999',1050,'PKR','Test donation',$returnUrl,$returnUrl,'registration-test',metadata:['callback_state'=>$token]);
    $http->reply($gw === 'meezan'
        ? ['errorCode'=>'0','orderId'=>'registered-meezan-id','formUrl'=>'https://bank.example.test/pay']
        : ['Transaction'=>['ResponseCode'=>'0','TransactionID'=>'registered-ubl-id','PaymentPage'=>'https://bank.example.test/pay']]);
    $reg = $gateway->register($request);
    check($reg->ok && $reg->hasRedirect(), "$gw original registration and handoff still work");
    $sent = $http->calls[array_key_last($http->calls)][1];
    check($gw === 'meezan' ? $sent['amount']==='1050' : $sent['Registration']['Amount']==='10.50', "$gw registration amount units unchanged");
    if ($gw==='etisalat') {
        check(str_contains($returnUrl,'OrderID=9999') && str_contains($returnUrl,'state=') && strlen($returnUrl)<=256,'UBL return URL includes OrderID and signed state within bank limit');
    }
    // A declined registration must not produce a payable handoff.
    $http->reply($gw === 'meezan' ? ['errorCode'=>'1','errorMessage'=>'Rejected'] : ['Transaction'=>['ResponseCode'=>'51','ResponseDescription'=>'Rejected']]);
    check(!$gateway->register($request)->ok,"$gw rejected registration remains rejected");
}
foreach (['meezan','etisalat'] as $gw) {
    foreach (['completed','failed','processing'] as $wanted) {
        [$tx,$token] = fixture($gw); $http->reply(bodyFor($gw, $tx, $wanted));
        $location = runReturn($gw, payloadFor($gw,$tx), $gw === 'meezan' ? 'GET' : 'POST');
        expectStatuses($tx, $wanted, "$gw verified $wanted updates both records");
        check(str_contains($location, match($wanted) {'completed'=>'/donation/success', 'failed'=>'/donation/failed', default=>'/donation/processing'}), "$gw $wanted return page");
        if ($wanted === 'completed') {
            $before = count($http->calls);
            runReturn($gw, payloadFor($gw,$tx));
            check(count($http->calls) === $before, "$gw duplicate success does not finalize again");
            expectStatuses($tx, 'completed', "$gw duplicate cannot downgrade completion");
        }
    }
    [$tx,$token] = fixture($gw);
    $http->queue[] = new HttpResult(0, '', [], 'simulated timeout');
    runReturn($gw, payloadFor($gw,$tx)); expectStatuses($tx,'processing',"$gw timeout stays processing");
    // Browser success claim with a bank pending response cannot complete anything.
    $http->reply(bodyFor($gw,$tx,'processing'));
    runReturn($gw, payloadFor($gw,$tx) + ['ResponseCode'=>'0','OrderStatus'=>'2']);
    expectStatuses($tx,'processing',"$gw forged browser success ignored");
    [$tx,$token] = fixture($gw, true); $http->reply(bodyFor($gw,$tx));
    runReturn($gw, payloadFor($gw,$tx)); expectStatuses($tx,'completed',"$gw late bank reference still verified");
    [$tx,$token] = fixture($gw, true); $before = count($http->calls);
    runReturn($gw, ['state'=>$token], 'GET');
    expectStatuses($tx,'processing',"$gw expired state alone cannot complete");
    check(count($http->calls)===$before,"$gw expired state without bank reference rejected");
    [$tx,$token] = fixture($gw); $owner = $lock->acquire('payment:transaction:' . $tx['id']);
    $before = count($http->calls); runReturn($gw,payloadFor($gw,$tx));
    check(count($http->calls)===$before,"$gw concurrent callback does not call bank");
    expectStatuses($tx,'processing',"$gw lock contention remains processing");
    $lock->release('payment:transaction:' . $tx['id'], $owner);
    [$tx,$token] = fixture($gw); $http->reply(bodyFor($gw,$tx),403);
    runReturn($gw,payloadFor($gw,$tx)); expectStatuses($tx,'processing',"$gw HTTP 403 success-shaped JSON is unverified");
    [$tx,$token] = fixture($gw); $bad = bodyFor($gw,$tx);
    if ($gw==='meezan') { $bad['Amount']='9999'; } else { $bad['Transaction']['Amount']='99.99'; }
    $http->reply($bad); runReturn($gw,payloadFor($gw,$tx)); expectStatuses($tx,'processing',"$gw amount mismatch rejected");
    [$tx,$token] = fixture($gw); $bad = bodyFor($gw,$tx);
    if ($gw==='meezan') { $bad['Currency']='840'; } else { $bad['Transaction']['Currency']='USD'; }
    $http->reply($bad); runReturn($gw,payloadFor($gw,$tx)); expectStatuses($tx,'processing',"$gw currency mismatch rejected");
    [$tx,$token] = fixture($gw); $bad = bodyFor($gw,$tx);
    if ($gw==='meezan') { $bad['orderId']='different-bank-order'; } else { $bad['Transaction']['TransactionID']='different-bank-txn'; }
    $http->reply($bad); runReturn($gw,payloadFor($gw,$tx)); expectStatuses($tx,'processing',"$gw bank identity mismatch rejected");
    [$tx,$token] = fixture($gw); $lower = bodyFor($gw,$tx);
    $lower = $gw === 'meezan' ? array_change_key_case($lower, CASE_LOWER) : ['transaction'=>array_change_key_case($lower['Transaction'], CASE_LOWER)];
    $http->reply($lower); runReturn($gw,payloadFor($gw,$tx)); expectStatuses($tx,'completed',"$gw lowercase bank fields accepted");
}
// The scheduled/admin reconciliation path must settle the same original rows.
foreach (['meezan','etisalat'] as $gw) {
    [$tx,$token] = fixture($gw); $http->reply(bodyFor($gw,$tx));
    $result = $service->reconcile((int)$tx['id']);
    check($result['changed'] && $result['status']==='completed',"$gw reconciliation confirms pending donation");
    expectStatuses($tx,'completed',"$gw reconciliation updates both statuses");
    $before=count($http->calls); $service->reconcile((int)$tx['id']);
    check(count($http->calls)===$before,"$gw completed reconciliation is idempotent");
    [$tx,$token] = fixture($gw); $http->reply(bodyFor($gw,$tx,'failed'));
    $service->reconcile((int)$tx['id']); expectStatuses($tx,'failed',"$gw reconciliation records confirmed failure");
}
// UBL versions returning only transaction identity, approval and verdict (optional echoes omitted).
[$tx,$token] = fixture('etisalat');
$http->reply(['Transaction'=>['ResponseCode'=>'0','TransactionID'=>$tx['provider_transaction_id'],'ApprovalCode'=>'ABC123']]);
runReturn('etisalat',payloadFor('etisalat',$tx)); expectStatuses($tx,'completed','UBL compact authenticated finalization');
[$tx,$token] = fixture('etisalat'); $http->reply(bodyFor('etisalat',$tx));
runReturn('etisalat',['state'=>$token],'GET'); expectStatuses($tx,'completed','UBL signed GET return without posted TransactionID');
[$tx,$token] = fixture('etisalat'); $bad=bodyFor('etisalat',$tx); unset($bad['Transaction']['ApprovalCode']);
$http->reply($bad); runReturn('etisalat',payloadFor('etisalat',$tx)); expectStatuses($tx,'processing','UBL success without approval is not accepted');
[$tx,$token] = fixture('etisalat'); $before=count($http->calls);
runReturn('etisalat',['state'=>$token,'TransactionID'=>'wrong']);
check(count($http->calls)===$before,'UBL contradictory callback ID rejected before finalization');
[$tx,$token] = fixture('etisalat'); $before=count($http->calls);
runReturn('etisalat',['OrderID'=>$tx['merchant_order_id']],'GET');
check(count($http->calls)===$before,'Merchant order number alone does not authorize verification');
[$tx,$token] = fixture('meezan'); $bad=bodyFor('meezan',$tx); $bad['ErrorCode']='5'; $bad['ErrorMessage']='API permission error';
$http->reply($bad); runReturn('meezan',payloadFor('meezan',$tx)); expectStatuses($tx,'processing','Meezan API error alongside paid status stays unverified');
[$tx,$token] = fixture('meezan'); $bad=bodyFor('meezan',$tx); unset($bad['Amount']);
$http->reply($bad); runReturn('meezan',payloadFor('meezan',$tx)); expectStatuses($tx,'processing','Meezan missing paid amount stays unverified');
// Atomic write failure: donation update must roll back if payment update fails.
[$tx,$token] = fixture('meezan');
$db->pdo()->exec("CREATE TRIGGER reject_payment BEFORE UPDATE OF status ON payment_transactions WHEN NEW.status='completed' BEGIN SELECT RAISE(ABORT,'simulated write failure'); END");
$http->reply(bodyFor('meezan',$tx));
try { runReturn('meezan',payloadFor('meezan',$tx)); throw new RuntimeException('Expected database failure'); }
catch (PDOException $e) { check(str_contains($e->getMessage(),'simulated write failure'),'Simulated payment write failure reached'); }
expectStatuses($tx,'processing','Atomic rollback keeps payment and donation consistent');
$db->pdo()->exec('DROP TRIGGER reject_payment');
$http->reply(bodyFor('meezan',$tx)); runReturn('meezan',payloadFor('meezan',$tx)); expectStatuses($tx,'completed','Callback retry after rollback completes original records');
check((int)$db->scalar('SELECT COUNT(*) FROM donations')===$fixtureCount && (int)$db->scalar('SELECT COUNT(*) FROM payment_transactions')===$fixtureCount,'Callbacks create no extra donations or payment transactions');
// Existing payment result markup remains unchanged; messages come from database records and are escaped.
$view = new View($root);
$html = $view->renderRaw('public/donate/response', ['outcome'=>'completed','reason'=>'','reference'=>'TEST','donation'=>null,'retryUrl'=>'/retry','statusUrl'=>'/status','transactions'=>[]]);
check(str_contains($html,'Payment Successful'),'Payment Successful visible on existing result component');
$html = $view->renderRaw('public/donate/response', ['outcome'=>'failed','reason'=>'failed','reference'=>'TEST','donation'=>null,'retryUrl'=>'/retry','statusUrl'=>'/status',
    'transactions'=>[['status'=>'failed','provider_response_description'=>'Card declined <script>alert(1)</script>']]]);
check(str_contains($html,'Payment Failed') && str_contains($html,'Card declined &lt;script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'Payment Failed and escaped stored bank error visible');
check($http->queue===[], 'Every expected bank fixture consumed');
echo "\n$count assertions passed. No live credentials or network used.\n";
