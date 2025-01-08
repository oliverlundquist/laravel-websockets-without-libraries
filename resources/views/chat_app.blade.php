<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Chat App</title>
        <script src="/js/axios.min.js"></script>
        <script src="/js/alpine.min.js" defer></script>
        <link rel="stylesheet" href="/css/bulma.css">
    </head>
    <body>
        <div class="container" x-data>
            <template x-if="$store.state.ready !== true">
                <nav class="panel">
                    <p class="panel-heading">Connecting ...</p>
                </nav>
            </template>
            <template x-if="$store.state.ready === true">
                <nav class="panel">
                    <p class="panel-heading" style="display: flex; justify-content: space-between; align-items: center">
                        <span>Chat App</span>
                        <span class="is-size-7">Rendered On: {{ config('instance.name') }}</span>
                    </p>
                    <p class="panel-tabs">
                        <template x-for="(chatMessages, chatName) in $store.chats" :key="chatName">
                            <a @click="openChat(chatName)" :class="$store.state.current_chat === chatName ? 'has-text-black is-active' : 'has-text-black'" x-text="chatName === 'broadcast' ? 'Public Chat' : 'User ' + chatName.slice(0, 5)"></a>
                        </template>
                    </p>
                    <div class="panel-block" style="padding: 0">
                        <div class="container">
                            <div class="columns is-gapless">
                                <div class="column">
                                    <div class="card" style="border-radius: 0; box-shadow: none">
                                        <header class="card-header" style="border-radius: 0;">
                                            <p class="card-header-title" style="display: flex; justify-content: space-between; align-items: center">
                                                <span x-text="$store.state.current_chat === 'broadcast' ? 'Public Chat' : 'User ' + $store.state.current_chat.slice(0, 5)"></span>
                                                <template x-if="$store.state.current_chat !== 'broadcast'">
                                                    <button @click="closeChat($store.state.current_chat)" class="delete" :disabled="$store.state.current_chat === 'broadcast'"></button>
                                                </template>
                                            </p>
                                        </header>
                                        <div class="card-content" style="padding-right: 0; max-width: 1025px;">
                                            <div class="content" style="max-height: 600px; overflow-y:scroll;">
                                                <template x-for="(chatMessage, index) in $store.chats[$store.state.current_chat]" :key="index">
                                                    <div class="mb-4" x-text="chatMessage" style="word-wrap: break-word;"></div>
                                                </template>
                                            </div>
                                        </div>
                                        <footer class="card-footer">
                                            <p class="card-footer-item" x-data="{ message: '' }">
                                                <input @keyup.enter="sendMessage(message); message = ''" class="input mr-2" type="text" placeholder="Send Message" x-model="message" />
                                                <button @click="sendMessage(message); message = ''" class="button">Send</button>
                                            </p>
                                        </footer>
                                    </div>
                                </div>
                                <div class="column is-one-fifth">
                                    <div class="card" style="border-radius: 0; box-shadow: none">
                                        <header class="card-header" style="border-radius: 0;">
                                            <p class="card-header-title">Active Users</p>
                                        </header>
                                        <div class="card-content">
                                            <div class="content">
                                                <template x-for="(user, index) in $store.users" :key="index">
                                                    <div>
                                                        <button @click="openChat(user.connection_id)" class="button mb-4" :disabled="user.connection_id === $store.state.connection_id" x-text="'User ' + user.connection_id.slice(0, 5) + (user.connection_id === $store.state.connection_id ? ' (You)' : '')"></button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>
            </template>
        </div>
        <script>
            const socket    = new WebSocket('ws://0.0.0.0');
            const users     = [];
            const pingTimer = setInterval(async () => {
                const state = Alpine.store('state');
                if (state.ready !== true) {
                    return;
                }
                await axios.get('/ping/' + state.connection_id);
            }, 1000);

            socket.onmessage = function(e) {
                const payload = JSON.parse(e.data);

                if (payload.event === 'welcome_message') {
                    Alpine.store('state', {
                        connection_id: payload.connection_id,
                        instance_name: payload.instance_name,
                        current_chat: 'broadcast',
                        ready: true
                    });
                }

                if (payload.event === 'user_list') {
                    Alpine.store('users', payload.users);
                }

                if (payload.event === 'public_chat') {
                    const state = Alpine.store('state');
                    const myConnectionId = state.connection_id;
                    if (myConnectionId === payload.origin.connection_id) {
                        return;
                    }
                    addNewMessage('broadcast', payload.message, payload.origin.connection_id);
                }

                if (payload.event === 'private_chat') {
                    const state = Alpine.store('state');
                    const myConnectionId = state.connection_id;
                    if (myConnectionId === payload.origin.connection_id) {
                        return;
                    }
                    addNewMessage(payload.origin.connection_id, payload.message, payload.origin.connection_id);
                }
            };
            function openChat(chatName)
            {
                const state = Alpine.store('state');
                const chats = Alpine.store('chats');
                // can't dm yourself
                if (chatName === state.connection_id) {
                    return;
                }
                state.current_chat = chatName;
                if (typeof chats[chatName] === 'undefined') {
                    chats[chatName] = [];
                }
                Alpine.store('state', state);
                Alpine.store('chats', chats);
            }
            function closeChat(chatName)
            {
                const state = Alpine.store('state');
                const chats = Alpine.store('chats');
                // can't close public channel
                if (chatName === 'broadcast') {
                    return;
                }
                state.current_chat = 'broadcast';
                delete chats[chatName];
                Alpine.store('state', state);
                Alpine.store('chats', chats);
            }
            function sendMessage(message)
            {
                if (message === '') {
                    return;
                }
                const state = Alpine.store('state');
                if (state.current_chat === 'broadcast') {
                    sendChatMessage(message)
                } else {
                    sendDmMessage(message)
                }
            }
            function addNewMessage(chatName, message, originConnectionId)
            {
                const state = Alpine.store('state');
                const chats = Alpine.store('chats');
                if (typeof chats[chatName] === 'undefined') {
                    chats[chatName] = [];
                }
                const formattedMessage = 'User ' + originConnectionId.slice(0, 5) + (state.connection_id === originConnectionId ? ' (You)' : '') + ': ' + message;
                chats[chatName].push(formattedMessage);
                Alpine.store('chats', chats);
            }
            function sendChatMessage(message)
            {
                const state = Alpine.store('state');
                const origin = {
                    connection_id: state.connection_id,
                    instance_name: state.instance_name
                }
                const destination = null;
                const payload = {
                    event: 'public_chat',
                    app_name: 'chat_app',
                    message,
                    origin,
                    destination
                }
                socket.send(JSON.stringify(payload));
                addNewMessage('broadcast', payload.message, origin.connection_id);
            }
            function sendDmMessage(message)
            {
                const state = Alpine.store('state');
                const users = Alpine.store('users');
                if (state.current_chat === 'broadcast') { // dm chat not selected
                    return;
                }
                const destinationUsers = users.filter(user => user.connection_id === state.current_chat);
                if (destinationUsers.length !== 1) {
                    return;
                }
                const destinationUser = destinationUsers[0];
                const origin = {
                    connection_id: state.connection_id,
                    instance_name: state.instance_name
                }
                const destination = {
                    connection_id: destinationUser.connection_id,
                    instance_name: destinationUser.instance_name
                }
                const payload = {
                    event: 'private_chat',
                    app_name: 'chat_app',
                    message,
                    origin,
                    destination
                }
                socket.send(JSON.stringify(payload));
                addNewMessage(destinationUser.connection_id, payload.message, origin.connection_id);
            }
            document.addEventListener('alpine:init', () => {
                Alpine.store('state', { ready: false });
                Alpine.store('users', []);
                Alpine.store('chats', { broadcast: [] });
            });
        </script>
    </body>
</html>
