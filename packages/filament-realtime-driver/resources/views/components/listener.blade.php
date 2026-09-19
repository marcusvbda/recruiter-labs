<div
    x-data="{
        event: {},
        __frdSocket: null,
        __frdSocketId: null,
        __frdConnect(url, channel, eventName) {
            this.__frdSocket = new WebSocket(url);

            this.__frdSocket.addEventListener('message', async (raw) => {
                const message = JSON.parse(raw.data);
                const data = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;

                if (message.event === 'pusher:connection_established') {
                    this.__frdSocketId = data.socket_id;
                    await this.__frdSubscribe(channel);
                    return;
                }

                if (message.event !== eventName) {
                    return;
                }

                this.event = data;
            });
        },
        async __frdSubscribe(channel) {
            const isProtected = channel.startsWith('private-') || channel.startsWith('presence-');
            const payload = { channel };

            if (isProtected) {
                Object.assign(payload, await this.__frdAuthorize(channel));
            }

            this.__frdSocket.send(JSON.stringify({ event: 'pusher:subscribe', data: payload }));
        },
        // private-*/presence-* channels must be signed server-side. Laravel's
        // own /broadcasting/auth endpoint (registered by routes/channels.php)
        // does this, guarded by whatever Broadcast::channel() authorization
        // is defined for the channel.
        async __frdAuthorize(channel) {
            const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
            const token = match ? decodeURIComponent(match[1]) : '';

            const response = await fetch('/broadcasting/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-XSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ socket_id: this.__frdSocketId, channel_name: channel }),
            });

            if (! response.ok) {
                console.error('[filament-realtime-driver] channel authorization failed', channel, response.status);
                return {};
            }

            return response.json();
        },
    }"
    x-init="__frdConnect(@js($socketUrl), @js($channel), @js($event))"
>
    {{ $slot }}
</div>
