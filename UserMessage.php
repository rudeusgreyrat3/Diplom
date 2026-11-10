<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMessage extends Model
{
    protected $fillable = ['user_id', 'complaint_id', 'title', 'body', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function user()      { return $this->belongsTo(User::class); }
    public function complaint() { return $this->belongsTo(Complaint::class); }
}
