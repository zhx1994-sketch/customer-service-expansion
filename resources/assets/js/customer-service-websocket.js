/**
 * 客服系统WebSocket客户端类
 * 
 * 提供完整的WebSocket连接和消息处理功能，包括：
 * - 自动连接和重连机制
 * - 频道订阅和管理
 * - 消息队列处理
 * - 连接状态监控
 * - 通知推送功能
 * 
 * @class CustomerServiceWebSocket
 * @version 1.0.0
 */

// WebSocket客户端类
class CustomerServiceWebSocket {
    constructor() {
        this.socket = null;                     // WebSocket连接对象
        this.reconnectAttempts = 0;            // 重连尝试次数
        this.maxReconnectAttempts = 5;         // 最大重连次数
        this.reconnectDelay = 1000;           // 重连延迟（毫秒）
        this.channels = new Map();             // 已订阅的频道
        this.messageQueue = [];               // 离线时的消息队列
        this.isConnected = false;              // 连接状态
    }

    /**
     * 建立WebSocket连接
     * 
     * @param {number} userId 用户ID
     * @param {string} userType 用户类型（'staff'或'user'）
     * @param {string|null} token 认证令牌
     */
    connect(userId, userType = 'staff', token = null) {
        this.userId = userId;
        this.userType = userType;
        this.token = token;

        // Pusher配置
        const config = {
            cluster: 'mt1',
            wsHost: window.CustomerServiceConfig?.websocket_host || '127.0.0.1',
            wsPort: window.CustomerServiceConfig?.websocket_port || 6001,
            wssPort: window.CustomerServiceConfig?.websocket_port || 6001,
            forceTLS: window.CustomerServiceConfig?.websocket_ssl || false,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/admin-api/customer-service/broadcasting/auth',
            auth: {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        };

        // 初始化Pusher实例
        this.pusher = new Pusher(window.CustomerServiceConfig?.pusher_key || 'cs_123456789', config);
        this.socket = this.pusher;

        // 绑定连接事件处理器
        this.pusher.connection.bind('connected', () => {
            console.log('WebSocket连接已建立');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.setupChannels();           // 设置频道订阅
            this.processMessageQueue();      // 处理离线消息队列
            this.updateConnectionStatus('connected');
        });

        // 绑定断开连接事件处理器
        this.pusher.connection.bind('disconnected', () => {
            console.log('WebSocket连接已断开');
            this.isConnected = false;
            this.updateConnectionStatus('disconnected');
            this.reconnect();              // 尝试重新连接
        });

        // 绑定错误事件处理器
        this.pusher.connection.bind('error', (error) => {
            console.error('WebSocket错误:', error);
            this.updateConnectionStatus('error');
        });
    }

    /**
     * 设置频道订阅
     * 
     * 订阅相关频道以接收消息推送：
     * - 对话频道：接收特定对话的消息
     * - 个人频道：接收个人消息
     */
    setupChannels() {
        // 监听对话消息频道
        this.subscribeToConversation = (conversationId) => {
            const channelName = `private-conversation.${conversationId}`;
            
            // 如果已经订阅过，直接返回现有频道
            if (this.channels.has(channelName)) {
                return this.channels.get(channelName);
            }

            // 订阅新频道
            const channel = this.pusher.subscribe(channelName);
            
            // 绑定新消息事件
            channel.bind('new.message', (data) => {
                this.handleNewMessage(data);
            });

            this.channels.set(channelName, channel);
            return channel;
        };

        // 订阅个人频道（用于接收特定用户的消息）
        const personalChannelName = `private-${this.userType}.${this.userId}`;
        const personalChannel = this.pusher.subscribe(personalChannelName);
        
        personalChannel.bind('new.message', (data) => {
            this.handlePersonalMessage(data);
        });

        this.channels.set(personalChannelName, personalChannel);
    }

    /**
     * 处理新收到的消息
     * 
     * @param {Object} messageData 消息数据对象
     */
    handleNewMessage(messageData) {
        console.log('收到新消息:', messageData);
        
        // 触发全局消息事件，供其他组件处理
        if (window.onCustomerServiceMessage) {
            window.onCustomerServiceMessage(messageData);
        }

        // 显示通知提醒用户
        this.showNotification('新消息', messageData.message.content);
    }

    /**
     * 处理个人消息
     * 
     * @param {Object} messageData 个人消息数据
     */
    handlePersonalMessage(messageData) {
        console.log('收到个人消息:', messageData);
        
        // 触发个人消息事件
        if (window.onPersonalMessage) {
            window.onPersonalMessage(messageData);
        }
    }

    /**
     * 显示通知
     * 
     * 支持两种通知方式：
     * - 浏览器原生通知（需要用户授权）
     * - 页面内自定义通知
     * 
     * @param {string} title 通知标题
     * @param {string} message 通知内容
     */
    showNotification(title, message) {
        // 浏览器通知（需要用户授权）
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: message,
                icon: '/vendor/customer-service-expansion/notification-icon.png'
            });
        }

        // 页面内通知
        if (window.showPageNotification) {
            window.showPageNotification(title, message);
        }
    }

    /**
     * 更新连接状态显示
     * 
     * 更新页面中的连接状态指示器和文字说明
     * 
     * @param {string} status 连接状态：'connected'、'connecting'、'disconnected'、'error'
     */
    updateConnectionStatus(status) {
        const statusElement = document.getElementById('connectionStatus');
        const statusDot = document.getElementById('statusDot');
        
        if (statusElement && statusDot) {
            statusDot.className = 'status-dot';
            
            // 根据状态设置不同的样式和文字
            switch(status) {
                case 'connected':
                    statusDot.classList.add('connected');
                    statusElement.textContent = '已连接';
                    break;
                case 'connecting':
                    statusDot.classList.add('connecting');
                    statusElement.textContent = '连接中';
                    break;
                case 'disconnected':
                case 'error':
                    statusDot.classList.add('disconnected');
                    statusElement.textContent = '连接已断开';
                    break;
            }
        }
    }

    /**
     * 断开WebSocket连接
     * 
     * 手动断开连接并清理资源
     */
    disconnect() {
        if (this.pusher) {
            this.pusher.disconnect();
        }
        this.isConnected = false;
    }

    /**
     * 自动重连机制
     * 
     * 在连接断开时自动尝试重新连接，
     * 使用指数退避算法避免频繁重连
     */
    reconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`尝试重连 ${this.reconnectAttempts}/${this.maxReconnectAttempts}`);
            
            // 延迟重连，延迟时间随重连次数增加
            setTimeout(() => {
                this.connect(this.userId, this.userType, this.token);
            }, this.reconnectDelay * this.reconnectAttempts);
        }
    }

    /**
     * 消息队列：添加消息
     * 
     * 在离线时将消息添加到队列中，
     * 等连接恢复后再处理
     * 
     * @param {Object} message 要缓存的消息
     */
    queueMessage(message) {
        this.messageQueue.push(message);
    }

    /**
     * 处理消息队列
     * 
     * 连接恢复后，处理队列中缓存的所有消息
     */
    processMessageQueue() {
        while (this.messageQueue.length > 0) {
            const message = this.messageQueue.shift();
            this.handleNewMessage(message);
        }
    }

    /**
     * 请求浏览器通知权限
     * 
     * 向用户请求显示桌面通知的权限
     */
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }
}

// 全局WebSocket实例
window.CustomerServiceWebSocket = CustomerServiceWebSocket;