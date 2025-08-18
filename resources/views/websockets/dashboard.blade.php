<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>WebSockets Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14"></script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@7.0.3/dist/web/pusher.min.js"></script>
</head>
<body>
    <div id="app" class="container mt-5">
        <div class="row">
            <div class="col-12">
                <h1>WebSockets Dashboard</h1>
                <div class="card mt-4">
                    <div class="card-header">
                        Conexión WebSocket
                    </div>
                    <div class="card-body">
                        <p>Estado: <span :class="{'text-success': connected, 'text-danger': !connected}">@{{ connectionStatus }}</span></p>
                        <p>Clientes conectados: @{{ clientsCount }}</p>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        Eventos
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li v-for="event in events" class="list-group-item">
                                <strong>@{{ event.type }}</strong>: @{{ event.data }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        new Vue({
            el: '#app',
            data: {
                connected: false,
                clientsCount: 0,
                events: [],
                pusher: null
            },
            computed: {
                connectionStatus() {
                    return this.connected ? 'Conectado' : 'Desconectado';
                }
            },
            methods: {
                connect() {
                    this.pusher = new Pusher('{{ $key }}', {
                        wsHost: '{{ $host }}',
                        wsPort: {{ $port }},
                        forceTLS: false,
                        enabledTransports: ['ws', 'wss'],
                        disableStats: true
                    });

                    this.pusher.connection.bind('connected', () => {
                        this.connected = true;
                    });

                    this.pusher.connection.bind('disconnected', () => {
                        this.connected = false;
                    });

                    this.pusher.connection.bind('error', error => {
                        this.addEvent('error', JSON.stringify(error));
                    });

                    // Suscribirse al canal de estadísticas
                    const channel = this.pusher.subscribe('{{ $app_name }}_statistics');
                    channel.bind('statistics', data => {
                        this.clientsCount = data.current_connections;
                        this.addEvent('statistics', JSON.stringify(data));
                    });
                },
                addEvent(type, data) {
                    this.events.unshift({ type, data, timestamp: new Date() });
                    if (this.events.length > 50) {
                        this.events.pop();
                    }
                }
            },
            mounted() {
                this.connect();
            }
        });
    </script>
</body>
</html>
