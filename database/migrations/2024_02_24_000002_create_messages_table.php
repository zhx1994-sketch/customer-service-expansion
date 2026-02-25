<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->comment('对话ID');
            $table->tinyInteger('sender_type')->comment('发送者：0用户 1客服');
            $table->unsignedBigInteger('sender_id')->nullable()->comment('发送者ID');
            $table->string('content_type', 20)->default('text')->comment('消息类型');
            $table->text('content')->comment('消息内容');
            $table->boolean('is_read')->default(false)->comment('是否已读');
            $table->timestamp('read_time')->nullable()->comment('阅读时间');
            $table->timestamps();
            
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->index('conversation_id');
            $table->index('sender_type');
            $table->unsignedInteger('deleted_at')->index()->comment('删除时间');
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};