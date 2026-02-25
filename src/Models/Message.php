<?php

/**
 * 消息模型
 * 
 * 管理客服对话中的消息记录，包含：
 * - 消息基本信息（内容、类型、发送者等）
 * - 已读状态管理
 * - 关联关系（对话、发送者）
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion\Models
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'conversation_id', // 所属对话ID
        'sender_type',     // 发送者类型：0用户 1客服
        'sender_id',      // 发送者ID
        'content_type',    // 消息类型：text、image、file等
        'content',        // 消息内容
        'is_read',        // 是否已读
        'read_time'       // 阅读时间
    ];

    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'is_read' => 'boolean',  // 将is_read转换为布尔值
        'read_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 关联对话模型
     * 
     * 获取消息所属的对话信息
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * 关联发送者模型
     * 
     * 根据发送者类型动态关联不同的模型：
     * - 发送者类型为0（用户）时，关联User模型
     * - 发送者类型为1（客服）时，关联AdminUser模型
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sender()
    {
        if ($this->sender_type === 0) {
            return $this->belongsTo(\App\Models\User::class, 'sender_id');
        } else {
            return $this->belongsTo(\Slowlyo\OwlAdmin\Models\AdminUser::class, 'sender_id');
        }
    }

    /**
     * 获取发送者的显示名称
     * 
     * 优先返回昵称，如果没有昵称则返回用户名，
     * 如果发送者被删除则返回"已删除用户"
     * 
     * @return string 发送者显示名称
     */
    public function getSenderNameAttribute()
    {
        if ($this->sender) {
            return $this->sender->nickname ?? $this->sender->name ?? '未知用户';
        }
        return '已删除用户';
    }

    /**
     * 获取已读状态的文本描述
     * 
     * 将布尔值转换为可读的中文描述
     * 
     * @return string 已读状态文本："已读"或"未读"
     */
    public function getReadStatusAttribute()
    {
        return $this->is_read ? '已读' : '未读';
    }

    /**
     * 获取已读状态的CSS类名
     * 
     * 用于前端显示不同的样式样式
     * 
     * @return string CSS类名："read"或"unread"
     */
    public function getReadStatusClassAttribute()
    {
        return $this->is_read ? 'read' : 'unread';
    }

    /**
     * 标记消息为已读状态
     * 
     * 将消息的已读状态设为true，并记录阅读时间。
     * 只有在消息未读时才会执行更新操作。
     * 
     * @return void
     */
    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,      // 标记为已读
                'read_time' => now()    // 记录阅读时间
            ]);
        }
    }
}