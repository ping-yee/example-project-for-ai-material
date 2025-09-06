<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date'
    ];

    protected $casts = [
        'due_date' => 'date',
        'priority' => 'integer'
    ];

    public function getPriorityTextAttribute()
    {
        return [
            1 => '低',
            2 => '中',
            3 => '高'
        ][$this->priority] ?? '未知';
    }

    public function getStatusTextAttribute()
    {
        return [
            'pending' => '待處理',
            'in_progress' => '進行中',
            'completed' => '已完成'
        ][$this->status] ?? '未知';
    }
}
