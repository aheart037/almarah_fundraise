<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fundraiser;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Mail\Mailer;
use InvalidArgumentException;

/**
 * Team fundraising: create a team, invite members by email, accept invitations.
 * Only the team owner may change the team or manage members.
 */
final class TeamController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private TeamRepository $teams,
        private UserRepository $users,
        private Mailer $mailer,
        private AuditService $audit
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        return $this->render('fundraiser/teams/index', [
            'pageTitle' => 'My teams',
            'teams'     => $this->teams->forUser((int) $this->auth->id()),
        ], 'layouts/dashboard');
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:4|max:120',
            'description' => 'nullable|string|max:2000',
            'goal'        => 'nullable',
            'campaign_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $name = trim((string) $request->input('name'));
        $userId = (int) $this->auth->id();

        $slug = Str::slug($name);
        $base = $slug !== '' ? $slug : 'team';
        $attempt = 0;
        do {
            $candidate = $attempt === 0 ? $base : $base . '-' . ($attempt + 1);
            $exists = $this->teams->findBySlug($candidate) !== null;
            $attempt++;
        } while ($exists && $attempt < 50);

        $goal = (string) $request->input('goal', '');
        $goalMinor = $goal !== '' ? (int) round(((float) str_replace(',', '', $goal)) * 100) : null;

        $teamId = $this->teams->create([
            'owner_user_id' => $userId,
            'campaign_id'   => (int) $request->input('campaign_id', 0) ?: null,
            'name'          => $name,
            'slug'          => $candidate,
            'description'   => $request->input('description'),
            'goal_minor'    => $goalMinor,
            'currency'      => (string) Config::get('app.currency', 'PKR'),
            'status'        => 'active',
        ]);

        // The creator always becomes the team owner.
        $this->teams->addMember($teamId, $userId, 'owner');

        $this->audit->log('team.created', 'team', (string) $teamId, ['name' => $name], $userId);

        $this->flashSuccess('Team created. Invite your friends and family next.');
        return $this->redirect('/dashboard/teams/' . $teamId);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $team = $this->requireMember($id);

        return $this->render('fundraiser/teams/show', [
            'pageTitle'   => (string) $team['name'],
            'team'        => $team,
            'members'     => $this->teams->members((int) $id),
            'invitations' => $this->teams->invitationsFor((int) $id),
            'isOwner'     => (int) $team['owner_user_id'] === (int) $this->auth->id(),
        ], 'layouts/dashboard');
    }

    public function update(Request $request): Response
    {
        $team = $this->requireOwner((int) $request->routeParam('id', 0));

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:4|max:120',
            'description' => 'nullable|string|max:2000',
            'goal'        => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $goal = (string) $request->input('goal', '');
        $goalMinor = $goal !== '' ? (int) round(((float) str_replace(',', '', $goal)) * 100) : null;

        $this->teams->update((int) $team['id'], [
            'name'        => trim((string) $request->input('name')),
            'description' => $request->input('description'),
            'goal_minor'  => $goalMinor,
        ]);

        $this->audit->log('team.updated', 'team', (string) $team['id'], [], (int) $this->auth->id());

        $this->flashSuccess('Team details updated.');
        return $this->redirect('/dashboard/teams/' . $team['id']);
    }

    public function invite(Request $request): Response
    {
        $team = $this->requireOwner((int) $request->routeParam('id', 0));

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:190',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $email = mb_strtolower(trim((string) $request->input('email')));

        // Never invite an existing member.
        $existing = $this->users->findByEmail($email);
        if ($existing !== null && $this->teams->isMember((int) $team['id'], (int) $existing['id'])) {
            $this->flashWarning('That person is already on your team.');
            return $this->redirect('/dashboard/teams/' . $team['id']);
        }

        $token = Str::randomToken(32);
        $ttlHours = 168;

        $this->teams->createInvitation([
            'team_id'    => (int) $team['id'],
            'email'      => $email,
            'token_hash' => hash('sha256', $token),
            'invited_by' => (int) $this->auth->id(),
            'status'     => 'pending',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + ($ttlHours * 3600)),
        ]);

        $inviter = $this->auth->fullName();
        $url = base_url('team-invitations/' . $token);

        $this->mailer->teamInvitation(
            $email,
            $email,
            $team,
            $inviter,
            $url
        );

        $this->audit->log('team.invitation_sent', 'team', (string) $team['id'], [
            'email_hash' => hash('sha256', $email),
        ], (int) $this->auth->id());

        $this->flashSuccess('Invitation sent to ' . $email . '.');
        return $this->redirect('/dashboard/teams/' . $team['id']);
    }

    public function removeMember(Request $request): Response
    {
        $team = $this->requireOwner((int) $request->routeParam('id', 0));
        $targetId = (int) $request->routeParam('userId', 0);

        if ($targetId === (int) $team['owner_user_id']) {
            $this->flashError('The team owner cannot be removed.');
            return $this->redirect('/dashboard/teams/' . $team['id']);
        }

        $this->teams->removeMember((int) $team['id'], $targetId);

        $this->audit->log('team.member_removed', 'team', (string) $team['id'], [
            'user_id' => $targetId,
        ], (int) $this->auth->id());

        $this->flashSuccess('Member removed from the team.');
        return $this->redirect('/dashboard/teams/' . $team['id']);
    }

    public function acceptInvitation(Request $request): Response
    {
        $invitation = $this->teams->findValidInvitation(hash('sha256', (string) $request->routeParam('token', '')));

        if ($invitation === null) {
            $this->flashError('That invitation is invalid, already used, or has expired.');
            return $this->redirect('/dashboard/teams');
        }

        $user = $this->auth->user();

        if ($user === null) {
            $this->flashError('Please sign in to accept this invitation.');
            return $this->redirect('/login');
        }

        // The invitation is addressed to one email address only.
        if (mb_strtolower((string) $user['email']) !== mb_strtolower((string) $invitation['email'])) {
            $this->flashError('This invitation was sent to ' . (string) $invitation['email'] . '. Sign in with that address to accept it.');
            return $this->redirect('/dashboard/teams');
        }

        $this->teams->addMember((int) $invitation['team_id'], (int) $user['id'], 'member');
        $this->teams->acceptInvitation((int) $invitation['id']);

        $this->audit->log('team.invitation_accepted', 'team', (string) $invitation['team_id'], [], (int) $user['id']);

        $this->flashSuccess('You have joined the team. Share the page to start raising.');
        return $this->redirect('/dashboard/teams/' . $invitation['team_id']);
    }

    /** @return array<string,mixed> */
    private function requireMember(int $id): array
    {
        $team = $this->teams->find($id);

        if ($team === null || !$this->teams->isMember($id, (int) $this->auth->id())) {
            abort(403, 'You do not have access to that team.');
        }

        return $team;
    }

    /** @return array<string,mixed> */
    private function requireOwner(int $id): array
    {
        $team = $this->teams->find($id);

        if ($team === null || (int) $team['owner_user_id'] !== (int) $this->auth->id()) {
            abort(403, 'Only the team owner can do that.');
        }

        return $team;
    }
}
