<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;
use App\Mail\Mailer;
use App\Repositories\FundraiserRepository;
use InvalidArgumentException;

/**
 * Fundraiser lifecycle: drafting, submission for review, moderation,
 * publishing, pausing and updates.
 *
 * Status transitions are explicit and audited. A fundraiser is publicly
 * visible only when status = 'published' AND approval_status = 'approved'.
 */
final class FundraiserService
{
    public function __construct(
        private FundraiserRepository $fundraisers,
        private Database $db,
        private Mailer $mailer,
        private AuditService $audit,
        private Logger $logger
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @return int fundraiser id
     */
    public function create(int $ownerId, array $data): int
    {
        $goalMinor = (int) $data['goal_minor'];
        $this->assertGoalWithinLimits($goalMinor);

        $title = trim((string) $data['title']);
        if (mb_strlen($title) < 5) {
            throw new InvalidArgumentException('Please give your fundraiser a title of at least 5 characters.');
        }

        $slug = $this->uniqueSlug((string) ($data['slug'] ?? '') !== '' ? (string) $data['slug'] : $title);

        $autoApprove = (bool) Config::get('app.features.auto_approve', false);

        $id = $this->fundraisers->create([
            'owner_user_id'    => $ownerId,
            'campaign_id'      => (int) ($data['campaign_id'] ?? 0),
            'category_id'      => (int) ($data['category_id'] ?? 0),
            'title'            => $title,
            'slug'             => $slug,
            'story'            => (string) $data['story'],
            'impact_statement' => $data['impact_statement'] ?? null,
            'cover_image_path' => $data['cover_image_path'] ?? null,
            'goal_minor'       => $goalMinor,
            'currency'         => (string) Config::get('app.currency', 'PKR'),
            'start_at'         => $data['start_at'] ?? gmdate('Y-m-d'),
            'end_at'           => $data['end_at'] ?? null,
            'status'           => 'draft',
            'approval_status'  => 'pending',
        ]);

        $this->audit->log('fundraiser.created', 'fundraiser', (string) $id, [
            'title' => $title,
            'goal'  => $goalMinor,
        ], $ownerId);

        if (!empty($data['submit_for_review'])) {
            $this->submitForReview($id, $ownerId);
        } elseif ($autoApprove) {
            $this->approve($id, $ownerId, 'Auto-approved by configuration.');
        }

        return $id;
    }

    /**
     * Fundraiser owners may edit their own record; admins may edit any.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $actorId, bool $isAdmin = false): void
    {
        $fundraiser = $this->fundraisers->find($id);
        if ($fundraiser === null) {
            throw new InvalidArgumentException('Fundraiser not found.');
        }

        if (!$isAdmin && (int) $fundraiser['owner_user_id'] !== $actorId) {
            throw new InvalidArgumentException('You can only edit your own fundraiser.');
        }

        $payload = [];

        if (isset($data['title'])) {
            $title = trim((string) $data['title']);
            if (mb_strlen($title) < 5) {
                throw new InvalidArgumentException('The title must be at least 5 characters.');
            }
            $payload['title'] = $title;
        }

        if (isset($data['slug']) && trim((string) $data['slug']) !== '') {
            $payload['slug'] = $this->uniqueSlug((string) $data['slug'], $id);
        }

        if (isset($data['story'])) {
            $payload['story'] = (string) $data['story'];
        }

        if (array_key_exists('impact_statement', $data)) {
            $payload['impact_statement'] = $data['impact_statement'];
        }

        if (array_key_exists('category_id', $data)) {
            $payload['category_id'] = (int) $data['category_id'] ?: null;
        }

        if (array_key_exists('campaign_id', $data)) {
            $payload['campaign_id'] = (int) $data['campaign_id'] ?: null;
        }

        if (array_key_exists('cover_image_path', $data) && $data['cover_image_path'] !== null) {
            $payload['cover_image_path'] = $data['cover_image_path'];
        }

        if (isset($data['end_at']) && $data['end_at'] !== '') {
            $payload['end_at'] = $data['end_at'];
        }

        // Goal changes follow policy: an owner may only change the goal while
        // the fundraiser has not yet been approved or has raised nothing.
        if (isset($data['goal_minor'])) {
            $newGoal = (int) $data['goal_minor'];
            $this->assertGoalWithinLimits($newGoal);

            if (!$isAdmin) {
                $raised = (int) ($fundraiser['raised_minor'] ?? 0);
                $approved = (string) $fundraiser['approval_status'] === 'approved';

                if ($approved && $raised > 0 && $newGoal < $raised) {
                    throw new InvalidArgumentException(
                        'Your goal cannot be set below the amount already raised. Please contact support to change this.'
                    );
                }
            }

            $payload['goal_minor'] = $newGoal;
        }

        if ($payload !== []) {
            $this->fundraisers->update($id, $payload);
        }

        $this->audit->log('fundraiser.updated', 'fundraiser', (string) $id, [
            'fields' => array_keys($payload),
            'by'     => $actorId,
        ], $actorId);
    }

    public function submitForReview(int $id, int $actorId): void
    {
        $fundraiser = $this->requireFundraiser($id);

        if (!$this->isOwner($fundraiser, $actorId)) {
            $admins = $this->isAdminActor($actorId);
            if (!$admins) {
                throw new InvalidArgumentException('You can only submit your own fundraiser.');
            }
        }

        if (trim((string) $fundraiser['story']) === '') {
            throw new InvalidArgumentException('Please add your story before submitting for review.');
        }

        $this->fundraisers->update($id, [
            'status'          => 'pending_review',
            'approval_status' => 'pending',
            'rejection_reason'=> null,
        ]);

        $this->audit->log('fundraiser.submitted', 'fundraiser', (string) $id, [], $actorId);

        $owner = $this->ownerOf($fundraiser);
        if ($owner !== null) {
            $this->mailer->fundraiserSubmitted(
                (string) $owner['email'],
                (string) $owner['first_name'],
                $this->fundraisers->find($id) ?? $fundraiser
            );
        }
    }

    public function approve(int $id, int $adminId, string $note = ''): void
    {
        $fundraiser = $this->requireFundraiser($id);

        $this->fundraisers->update($id, [
            'status'           => 'published',
            'approval_status'  => 'approved',
            'rejection_reason' => null,
            'published_at'     => $fundraiser['published_at'] ?: gmdate('Y-m-d H:i:s'),
        ]);

        $this->audit->log('fundraiser.approved', 'fundraiser', (string) $id, ['note' => $note], $adminId);
        $this->logger->info('Fundraiser approved', ['id' => $id, 'admin' => $adminId]);

        // Approval is the primary operation; the notification is a secondary
        // side effect and must not turn a committed approval into an HTTP 500.
        try {
            $owner = $this->ownerOf($fundraiser);
            if ($owner !== null) {
                $approvedFundraiser = $this->fundraisers->find($id) ?? $fundraiser;
                $this->mailer->fundraiserApproved(
                    (string) $owner['email'],
                    (string) $owner['first_name'],
                    $approvedFundraiser
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Fundraiser was approved but its owner notification could not be queued', [
                'fundraiser_id' => $id,
                'admin_id'      => $adminId,
                'exception'     => get_class($e),
                'message'       => $e->getMessage(),
            ]);
        }
    }

    public function reject(int $id, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        $fundraiser = $this->requireFundraiser($id);

        $this->fundraisers->update($id, [
            'status'           => 'rejected',
            'approval_status'  => 'rejected',
            'rejection_reason' => Str::limit($reason, 1000, ''),
        ]);

        $this->audit->log('fundraiser.rejected', 'fundraiser', (string) $id, ['reason' => $reason], $adminId);

        $owner = $this->ownerOf($fundraiser);
        if ($owner !== null) {
            $this->mailer->fundraiserRejected(
                (string) $owner['email'],
                (string) $owner['first_name'],
                $this->fundraisers->find($id) ?? $fundraiser,
                $reason
            );
        }
    }

    public function requestChanges(int $id, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Please describe the changes required.');
        }

        $fundraiser = $this->requireFundraiser($id);

        $this->fundraisers->update($id, [
            'status'           => 'changes_requested',
            'approval_status'  => 'changes_requested',
            'rejection_reason' => Str::limit($reason, 1000, ''),
        ]);

        $this->audit->log('fundraiser.changes_requested', 'fundraiser', (string) $id, ['reason' => $reason], $adminId);

        $owner = $this->ownerOf($fundraiser);
        if ($owner !== null) {
            $this->mailer->fundraiserRejected(
                (string) $owner['email'],
                (string) $owner['first_name'],
                $this->fundraisers->find($id) ?? $fundraiser,
                $reason
            );
        }
    }

    public function pause(int $id, int $adminId, string $reason): void
    {
        $fundraiser = $this->requireFundraiser($id);

        $this->fundraisers->update($id, ['status' => 'paused']);

        $this->audit->log('fundraiser.paused', 'fundraiser', (string) $id, ['reason' => $reason], $adminId);

        $owner = $this->ownerOf($fundraiser);
        if ($owner !== null) {
            $this->mailer->fundraiserPaused(
                (string) $owner['email'],
                (string) $owner['first_name'],
                $this->fundraisers->find($id) ?? $fundraiser,
                $reason !== '' ? $reason : 'Paused by an administrator.'
            );
        }
    }

    public function publish(int $id, int $adminId): void
    {
        $this->requireFundraiser($id);
        $this->fundraisers->update($id, ['status' => 'published', 'approval_status' => 'approved']);
        $this->audit->log('fundraiser.published', 'fundraiser', (string) $id, [], $adminId);
    }

    public function unpublish(int $id, int $adminId): void
    {
        $this->requireFundraiser($id);
        $this->fundraisers->update($id, ['status' => 'paused']);
        $this->audit->log('fundraiser.unpublished', 'fundraiser', (string) $id, [], $adminId);
    }

    public function archive(int $id, int $adminId): void
    {
        $this->requireFundraiser($id);
        $this->fundraisers->update($id, ['status' => 'archived']);
        $this->audit->log('fundraiser.archived', 'fundraiser', (string) $id, [], $adminId);
    }

    public function setFeatured(int $id, bool $featured, int $adminId): void
    {
        $this->requireFundraiser($id);
        $this->fundraisers->update($id, ['featured' => $featured ? 1 : 0]);
        $this->audit->log('fundraiser.featured', 'fundraiser', (string) $id, ['featured' => $featured], $adminId);
    }

    public function softDelete(int $id, int $adminId): void
    {
        $this->requireFundraiser($id);
        $this->fundraisers->softDelete($id);
        $this->audit->log('fundraiser.deleted', 'fundraiser', (string) $id, [], $adminId);
    }

    // -------------------------------------------------------------- updates

    /**
     * Publish an update on a fundraiser. Only the owner (or an admin) may do so.
     *
     * @param array<string,mixed> $data
     */
    public function publishUpdate(int $fundraiserId, int $actorId, array $data, bool $isAdmin = false): int
    {
        $fundraiser = $this->requireFundraiser($fundraiserId);

        if (!$isAdmin && (int) $fundraiser['owner_user_id'] !== $actorId) {
            throw new InvalidArgumentException('You can only post updates on your own fundraiser.');
        }

        $title = trim((string) $data['title']);
        $body = trim((string) $data['body']);

        if ($title === '' || $body === '') {
            throw new InvalidArgumentException('An update needs both a title and a message.');
        }

        $id = $this->fundraisers->createUpdate([
            'fundraiser_id'  => $fundraiserId,
            'author_user_id' => $actorId,
            'title'          => Str::limit($title, 180, ''),
            'body'           => Str::limit($body, 20000, ''),
            'image_path'     => $data['image_path'] ?? null,
            'status'         => 'published',
            'published_at'   => gmdate('Y-m-d H:i:s'),
        ]);

        $this->audit->log('fundraiser.update_published', 'fundraiser', (string) $fundraiserId, [
            'update_id' => $id,
        ], $actorId);

        $owner = $this->ownerOf($fundraiser);
        if ($owner !== null) {
            $this->mailer->fundraiserUpdatePublished(
                (string) $owner['email'],
                (string) $owner['first_name'],
                $fundraiser,
                $title
            );
        }

        return $id;
    }

    public function hideUpdate(int $updateId, int $adminId): void
    {
        $this->fundraisers->updateUpdate($updateId, ['status' => 'hidden']);
        $this->audit->log('fundraiser.update_hidden', 'fundraiser_update', (string) $updateId, [], $adminId);
    }

    // -------------------------------------------------------------- helpers

    public function uniqueSlug(string $source, ?int $exceptId = null, int $attempt = 0): string
    {
        $base = Str::slug($source);
        $candidate = $attempt === 0 ? $base : $base . '-' . $attempt;

        if ($this->fundraisers->findBySlug($candidate, $exceptId) === null) {
            return $candidate;
        }

        if ($attempt > 50) {
            return $base . '-' . Str::randomHex(3);
        }

        return $this->uniqueSlug($source, $exceptId, $attempt + 1);
    }

    private function assertGoalWithinLimits(int $goalMinor): void
    {
        $min = (int) Config::get('app.fundraisers.goal_min_minor', 1000000);
        $max = (int) Config::get('app.fundraisers.goal_max_minor', 2000000000);

        if ($goalMinor < $min) {
            throw new InvalidArgumentException('Your goal must be at least ' . money($min) . '.');
        }
        if ($goalMinor > $max) {
            throw new InvalidArgumentException('Your goal may not exceed ' . money($max) . '.');
        }
    }

    /** @return array<string,mixed> */
    private function requireFundraiser(int $id): array
    {
        $fundraiser = $this->fundraisers->find($id);
        if ($fundraiser === null) {
            throw new InvalidArgumentException('Fundraiser not found.');
        }
        return $fundraiser;
    }

    /** @param array<string,mixed> $fundraiser */
    private function isOwner(array $fundraiser, int $actorId): bool
    {
        return (int) $fundraiser['owner_user_id'] === $actorId;
    }

    private function isAdminActor(int $actorId): bool
    {
        $roles = $this->db->select(
            'SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id',
            ['id' => $actorId]
        );

        foreach ($roles as $role) {
            if (in_array((string) $role['name'], ['admin', 'super_admin'], true)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $fundraiser @return array<string,mixed>|null */
    private function ownerOf(array $fundraiser): ?array
    {
        return $this->db->selectOne('SELECT * FROM users WHERE id = :id', ['id' => (int) $fundraiser['owner_user_id']]);
    }

    /**
     * Fundraisers ending within $days that have not yet been notified today.
     *
     * @return array<int,array<string,mixed>>
     */
    public function endingSoon(int $days = 7): array
    {
        return $this->db->select(
            "SELECT f.*, u.email AS owner_email, u.first_name
             FROM fundraisers f JOIN users u ON u.id = f.owner_user_id
             WHERE f.status = 'published' AND f.deleted_at IS NULL
               AND f.end_at IS NOT NULL
               AND f.end_at BETWEEN UTC_DATE() AND DATE_ADD(UTC_DATE(), INTERVAL :days DAY)",
            ['days' => $days]
        );
    }
}
