import React, { useState, useEffect, useContext, useRef } from 'react';
import { AuthContext } from '../Context/AuthContext';

const SendIcon = () => (
    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
        <path d="M1.101 21.757 23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"></path>
    </svg>
);
const ReplyIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <polyline points="9 10 4 15 9 20"></polyline><path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
    </svg>
);

const ChatWindow = ({ selectedUser, roomId, onProfileClick, socket }) => {
    const { api, user } = useContext(AuthContext);
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const messagesEndRef = useRef(null);

    // BEZPEČNÁ INICIALIZACE STATUSU
    const [headerStatus, setHeaderStatus] = useState(selectedUser?.status ?? 'offline');

    const [activeMenuId, setActiveMenuId] = useState(null);
    const [editingMessage, setEditingMessage] = useState(null);
    const [replyingTo, setReplyingTo] = useState(null);
    const [showScrollButton, setShowScrollButton] = useState(false);

    // Reset statusu při přepnutí uživatele
    useEffect(() => {
        setHeaderStatus(selectedUser?.status ?? 'offline');
    }, [selectedUser]);

    if (!selectedUser) return null;

    // --- 1. PRESENCE (Jsem v místnosti) ---
    useEffect(() => {
        if (!roomId || !socket || socket.readyState !== WebSocket.OPEN) return;

        socket.send(JSON.stringify({ type: 'presence:set_active_room', roomId: roomId }));

        api.post('/chat/mark-read', { room_id: roomId })
            .then(() => window.dispatchEvent(new CustomEvent('chat-update', { detail: { refresh: true } })))
            .catch(() => {});

        const handleStatusChange = (e) => {
            if (selectedUser?.id && String(e.detail.userId) === String(selectedUser.id)) {
                setHeaderStatus(e.detail.status);
            }
        };
        window.addEventListener('friend-status-change', handleStatusChange);

        return () => {
            window.removeEventListener('friend-status-change', handleStatusChange);
            if (socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({ type: 'presence:set_active_room', roomId: null }));
            }
        };
    }, [roomId, socket, selectedUser?.id, api]);

    // --- 2. NAČTENÍ HISTORIE ---
    useEffect(() => {
        if (roomId) {
            api.get(`/messages/history?room_id=${roomId}`)
                .then(res => setMessages(res.data))
                .catch(console.error);
        }
    }, [roomId, api]);

    useEffect(() => scrollToBottom(), [messages]);

    // --- 3. LIVE ZPRÁVY ---
    useEffect(() => {
        const handleUpdate = (e) => {
            const data = e.detail;
            if (!data || (data.roomId && String(data.roomId) !== String(roomId))) return;

            if (data.type === 'message:new') {
                setMessages(prev => {
                    if (prev.some(m => String(m.id) === String(data.message.id))) return prev;
                    return [...prev, data.message];
                });
                api.post('/chat/mark-read', { room_id: roomId });
            }
            else if (data.type === 'message_update') {
                setMessages(prev => prev.map(m => String(m.id) === String(data.msgId) ? { ...m, content: data.newContent, is_edited: true } : m));
            }
            else if (data.type === 'message_delete') {
                setMessages(prev => prev.map(m => String(m.id) === String(data.msgId) ? { ...m, is_deleted: true } : m));
            }
        };
        window.addEventListener('chat-update', handleUpdate);
        return () => window.removeEventListener('chat-update', handleUpdate);
    }, [roomId, api]);

    const scrollToBottom = () => messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    const handleScroll = (e) => setShowScrollButton(e.target.scrollHeight - e.target.scrollTop - e.target.clientHeight > 150);

    const startEditing = (msg) => { setEditingMessage(msg); setReplyingTo(null); setNewMessage(msg.content); setActiveMenuId(null); };
    const startReplying = (msg) => { setReplyingTo(msg); setEditingMessage(null); setActiveMenuId(null); };
    const cancelAction = () => { setEditingMessage(null); setReplyingTo(null); setNewMessage(''); };

    const handleSend = async (e) => {
        e.preventDefault();
        if (!newMessage.trim()) return;

        if (editingMessage) {
            try {
                await api.post('/messages/update', { message_id: editingMessage.id, content: newMessage });
                setMessages(prev => prev.map(m => m.id === editingMessage.id ? { ...m, content: newMessage, is_edited: true } : m));
                if (socket?.readyState === WebSocket.OPEN) {
                    socket.send(JSON.stringify({ type: 'message_update', roomId, msgId: editingMessage.id, newContent: newMessage }));
                }
                cancelAction();
            } catch (err) { alert("Chyba úpravy"); }
        } else {
            try {
                const res = await api.post('/messages/send', { room_id: roomId, content: newMessage, reply_to_id: replyingTo?.id });
                const savedMsg = res.data.data;
                setNewMessage('');
                setReplyingTo(null);
                setMessages(prev => [...prev, savedMsg]);
                if (socket?.readyState === WebSocket.OPEN) {
                    socket.send(JSON.stringify({ type: 'message:new', roomId, message: savedMsg }));
                }
            } catch (err) {
                if (err.response && err.response.status === 403) {
                    alert("Byli jste odebráni z této skupiny.");
                    window.location.reload();
                }
            }
        }
    };

    const deleteMessage = async (msgId) => {
        if (!window.confirm("Smazat?")) return;
        try {
            await api.post('/messages/delete', { message_id: msgId });
            setMessages(prev => prev.map(m => m.id === msgId ? { ...m, is_deleted: true } : m));
            if (socket?.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({ type: 'message_delete', roomId, msgId }));
            }
        } catch (e) {}
    };

    const formatTime = (d) => new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const myId = user.id || user.sub;
    const activeAction = editingMessage || replyingTo;
    const isGroup = selectedUser.type === 'group';
    let displayName = isGroup ? selectedUser.name : selectedUser.username;

    if (!isGroup && name && name.startsWith('deleted_')) {
        name = "Smazaný uživatel";
    }

    const fallbackAvatar = isGroup ? `https://api.dicebear.com/7.x/initials/svg?seed=${displayName}` : `https://api.dicebear.com/7.x/avataaars/svg?seed=${displayName}`;
    const displayAvatar = (selectedUser.avatar_url && selectedUser.avatar_url.trim() !== '') ? selectedUser.avatar_url : fallbackAvatar;

    return (
        <div className="chat-room">
            <div className="chat-header">
                <div className="chat-user-info clickable" onClick={() => onProfileClick(selectedUser)}>
                    <img src={displayAvatar} alt="Avatar" />
                    <div className="user-text-info">
                        <h3>{displayName}</h3>
                        <span className="status-text" style={{ color: headerStatus === 'online' ? '#43b581' : '#a0a0a0' }}>
                            {isGroup ? 'Skupina' : (headerStatus === 'online' ? 'Online' : 'Offline')}
                        </span>
                    </div>
                </div>
            </div>

            <div className="messages-area" onScroll={handleScroll}>
                {messages.length === 0 ? <div className="empty-chat-placeholder"><p>Zatím žádné zprávy.</p></div> :
                    messages.map((msg, index) => {
                        const isMe = String(msg.sender_id) === String(myId);
                        const prevMsg = messages[index - 1];
                        const repliedMsg = msg.reply_to_id ? messages.find(m => m.id === msg.reply_to_id) : null;
                        const showSenderInfo = !isMe && isGroup && (!prevMsg || String(prevMsg.sender_id) !== String(msg.sender_id));

                        return (
                            <div key={index} className={`message-container ${isMe ? 'my-msg-container' : 'friend-msg-container'}`}>
                                {showSenderInfo && (
                                    <div className="message-sender-header">
                                        <img src={msg.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${msg.username}`} alt={msg.username} className="sender-mini-avatar" />
                                        <span className="sender-name">{msg.username}</span>
                                    </div>
                                )}
                                <div className={`message-row ${isMe ? 'my-message' : 'friend-message'} ${showSenderInfo ? 'first-in-group' : ''}`}>
                                    <div className="message-actions">
                                        {!msg.is_deleted && (
                                            <>
                                                <button className="dots-btn" onClick={(e) => { e.stopPropagation(); setActiveMenuId(activeMenuId === msg.id ? null : msg.id); }}>⋮</button>
                                                {activeMenuId === msg.id && (
                                                    <div className="action-menu">
                                                        <button onClick={() => startReplying(msg)}>↩ Odpovědět</button>
                                                        {isMe && (
                                                            <>
                                                                <button onClick={() => startEditing(msg)}>✎ Upravit</button>
                                                                <button onClick={() => deleteMessage(msg.id)} className="delete-opt">🗑 Smazat</button>
                                                            </>
                                                        )}
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>
                                    <div className={`message-bubble ${msg.is_deleted ? 'deleted' : ''}`}>
                                        {msg.reply_to_id && !msg.is_deleted && (
                                            <div className="reply-quote">
                                                {repliedMsg ? <><span className="reply-author">{String(repliedMsg.sender_id) === String(myId) ? "Ty" : repliedMsg.username}</span><span className="reply-content">{repliedMsg.content}</span></> : <i>Zpráva nedostupná</i>}
                                            </div>
                                        )}
                                        {msg.is_deleted ? <span className="deleted-text">🚫 <i>Odstraněno</i></span> : <span>{msg.content}</span>}
                                        <div className="msg-meta"><span className="msg-time">{formatTime(msg.created_at)}</span>{msg.is_edited && !msg.is_deleted && <span> (upraveno)</span>}</div>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                }
                <div ref={messagesEndRef} />
                {activeAction ? <button className="scroll-bottom-btn cancel-float-btn" onClick={cancelAction}>✕</button> : showScrollButton && <button className="scroll-bottom-btn" onClick={scrollToBottom}>↓</button>}
            </div>

            <form className="chat-input-area" onSubmit={handleSend}>
                {activeAction && <div className="editing-banner"><span>{editingMessage ? "Upravujete zprávu..." : "Odpověď..."}</span></div>}
                <input type="text" placeholder="Napište zprávu..." value={newMessage} onChange={(e) => setNewMessage(e.target.value)} autoFocus />
                <button type="submit" disabled={!newMessage.trim()}>{editingMessage ? "💾" : replyingTo ? <ReplyIcon /> : <SendIcon />}</button>
            </form>
        </div>
    );
};

export default ChatWindow;