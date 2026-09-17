<?php

namespace App\Domains\Comment\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $project_id
 * @property string $user_id
 * @property string|null $parent_id
 * @property string $content
 * @property string $position_x
 * @property string $position_y
 * @property string $position_z
 * @property string|null $normal_x
 * @property string|null $normal_y
 * @property string|null $normal_z
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    protected $table = 'comments';

    protected $fillable = [
        'project_id',
        'user_id',
        'parent_id',
        'content',
        'position_x',
        'position_y',
        'position_z',
        'normal_x',
        'normal_y',
        'normal_z',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'position_x' => 'float',
            'position_y' => 'float',
            'position_z' => 'float',
            'normal_x' => 'float',
            'normal_y' => 'float',
            'normal_z' => 'float',
        ];
    }

    /**
     * The project this comment is pinned to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * The user who posted the comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Parent comment for threaded replies.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Threaded replies to this comment.
     *
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    /**
     * Whether this is a root pin comment or a reply.
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }
}
