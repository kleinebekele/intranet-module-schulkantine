<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kategorie-Freigabe eines Kindes. Fehlt die Zeile, ist alles erlaubt (Standard).
 * Eine Zeile schränkt gezielt ein (may_preorder / may_walkin).
 */
class ChildCategoryPermission extends Model
{
    protected $table = 'kantine_child_category_perms';

    protected $fillable = [
        'user_id',
        'category_id',
        'may_preorder',
        'may_walkin',
    ];

    protected function casts(): array
    {
        return [
            'may_preorder' => 'boolean',
            'may_walkin' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Freigaben eines Kindes als Map category_id => Zeile. */
    public static function mapFor(int $userId)
    {
        return static::where('user_id', $userId)->get()->keyBy('category_id');
    }

    /** Darf das Kind diese Kategorie vorbestellen? (Standard: ja) */
    public static function canPreorder(int $userId, int $categoryId): bool
    {
        $row = static::where('user_id', $userId)->where('category_id', $categoryId)->first();

        return $row ? $row->may_preorder : true;
    }

    /** Darf das Kind diese Kategorie spontan kaufen? (Standard: ja) */
    public static function canWalkin(int $userId, int $categoryId): bool
    {
        $row = static::where('user_id', $userId)->where('category_id', $categoryId)->first();

        return $row ? $row->may_walkin : true;
    }
}
