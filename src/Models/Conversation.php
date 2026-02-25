<?php

/**
 * 对话模型
 * 
 * 管理客服对话的核心数据模型，包含：
 * - 对话基本信息（标题、状态、优先级等）
 * - 关联关系（用户、客服、消息）
 * - 业务方法（已读标记、统计等）
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion\Models
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'session_id',        // 会话唯一标识符
        'user_id',           // 发起对话的用户ID
        'staff_id',          // 处理对话的客服ID（可为空）
        'status',            // 对话状态：0等待中 1进行中 2已结束
        'priority',          // 优先级：0普通 1紧急
        'title',            // 对话标题
        'last_message_time', // 最后消息时间，用于排序
        'end_time'          // 对话结束时间
    ];

    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'last_message_time' => 'datetime',
        'end_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 关联用户模型
     * 
     * 获取发起对话的用户信息
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * 关联客服模型
     * 
     * 获取负责处理该对话的客服信息
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function staff()
    {
        return $this->belongsTo(\Slowlyo\OwlAdmin\Models\AdminUser::class, 'staff_id');
    }

    /**
     * 关联消息模型
     * 
     * 获取该对话下的所有消息记录
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * 关联最新消息
     * 
     * 获取该对话的最新一条消息，用于显示在对话列表中
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    /**
     * 获取状态文本描述
     * 
     * 将数字状态码转换为可读的中文描述
     * 
     * @return string 状态文本
     */
    public function getStatusTextAttribute()
    {
        $statusMap = [
            0 => '等待中',
            1 => '进行中', 
            2 => '已结束'
        ];
        return $statusMap[$this->status] ?? '未知';
    }

    /**
     * 获取对话中未读消息数量
     * 
     * 计算该对话中用户发送但客服尚未阅读的消息数量
     * 用于在对话列表中显示未读标识
     * 
     * @return int 未读消息数量
     */
    public function getUnreadCountAttribute()
    {
        return $this->messages()
            ->where('sender_type', 0) // 只统计用户发送的消息
            ->where('is_read', 0)   // 未读状态
            ->count();
    }

    /**
     * 获取对话中消息总数
     * 
     * 统计该对话中的所有消息数量（包括用户和客服的消息）
     * 
     * @return int 消息总数
     */
    public function getMessageCountAttribute()
    {
        return $this->messages()->count();
    }

    /**
     * 检查对话是否有未读消息
     * 
     * 判断该对话是否包含用户发送但客服未读的消息
     * 
     * @return bool true表示有未读消息，false表示无未读消息
     */
    public function hasUnreadMessages()
    {
        return $this->unread_count > 0;
    }

    /**
     * 标记对话中所有用户消息为已读
     * 
     * 将该对话中所有用户发送且未读的消息标记为已读状态，
     * 同时记录阅读时间。通常在客服查看对话详情时调用。
     * 
     * @return void
     */
    public function markAllMessagesAsRead()
    {
        $this->messages()
            ->where('sender_type', 0) // 只处理用户消息
            ->where('is_read', 0)      // 只处理未读消息
            ->update([
                'is_read' => true,      // 标记为已读
                'read_time' => now()    // 记录阅读时间
            ]);
    }

    /**
     * 获取最新消息内容的预览文本
     * 
     * 从最新消息中提取内容，并截取前30个字符作为预览。
     * 如果超过30个字符，会添加省略号。
     * 
     * @return string|null 最新消息预览文本，如果无消息则返回null
     */
    public function getLastMessageContentAttribute()
    {
        if ($this->lastMessage) {
            $content = $this->lastMessage->content;
            return strlen($content) > 30 ? substr($content, 0, 30) . '...' : $content;
        }
        return null;
    }
}