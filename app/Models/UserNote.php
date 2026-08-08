<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A private note the owner keeps about one of their connections. */
class UserNote extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'connection_id', 'note'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
