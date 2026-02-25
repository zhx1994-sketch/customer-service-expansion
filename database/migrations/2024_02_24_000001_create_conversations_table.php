<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->unique()->comment('会话ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->unsignedBigInteger('staff_id')->nullable()->comment('客服ID');
            $table->tinyInteger('status')->default(0)->comment('状态：0等待中 1进行中 2已结束');
            $table->tinyInteger('priority')->default(0)->comment('优先级：0普通 1紧急');
            $table->string('title', 200)->nullable()->comment('对话标题');
            $table->timestamp('last_message_time')->nullable()->comment('最后消息时间');
            $table->timestamp('end_time')->nullable()->comment('结束时间');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('staff_id')->references('id')->on('admin_users');
            
            $table->index('user_id');
            $table->index('staff_id');
            $table->index('status');
            $table->index('last_message_time');
            $table->unsignedInteger('deleted_at')->index()->comment('删除时间');

        });
    }

    public function down()
    {
        Schema::dropIfExists('conversations');
    }
};