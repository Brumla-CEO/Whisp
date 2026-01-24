import React, { useState, useEffect, useContext, useRef } from 'react';
import { AuthContext } from '../context/AuthContext';

// Ikona odeslání
const SendIcon = () => (
    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
        <path d="M1.101 21.757 23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"></path>
    </svg>
);

const ChatWindow = ({ selectedUser, roomId, onProfileClick }) => {
    const { api, user } = useContext(AuthContext);
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const messagesEndRef = useRef(null);

    // Stavy pro CRUD akce
    const [activeMenuId, setActiveMenuId] = useState(null); // ID zprávy, kde je otevřené menu
    const [editingMessage, setEditingMessage] = useState(null); // Objekt zprávy, kterou upravujeme

    // Načtení historie
    const loadMessages = async () => {
        if (!roomId) return;
        try {
            const res = await api.get(`/messages/history?room_id=${roomId}`);
            setMessages(res.data);
        } catch (err) {
            console.error("Chyba načítání zpráv", err);
        }
    };

    // Polling zpráv
    useEffect(() => {
        loadMessages();
        const interval = setInterval(loadMessages, 3000);
        return () => clearInterval(interval);
    }, [roomId]);

    // Auto-scroll dolů (jen pokud needitujeme, aby nám to neskákalo pod rukama)
    /*useEffect(() => {
        if (!editingMessage) {
            messagesEndRef.current?.scrollIntoView({ behavior: "auto" });
        }
    }, [messages, editingMessage]);*/

    // Zavření menu když kliknu jinam
    useEffect(() => {
        const handleClickOutside = () => setActiveMenuId(null);
        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    }, []);

    const handleSend = async (e) => {
        e.preventDefault();
        if (!newMessage.trim()) return;

        // POKUD EDITUJEME EXISTUJÍCÍ ZPRÁVU
        if (editingMessage) {
            try {
                await api.post('/messages/update', {
                    message_id: editingMessage.id,
                    content: newMessage
                });

                // Optimistický update v UI
                setMessages(prev => prev.map(m =>
                    m.id === editingMessage.id
                        ? { ...m, content: newMessage, is_edited: true }
                        : m
                ));

                setEditingMessage(null);
                setNewMessage('');
            } catch (err) {
                alert("Chyba při úpravě zprávy");
            }
            return;
        }

        // POKUD POSÍLÁME NOVOU ZPRÁVU
        try {
            // Optimistický update
            const tempMessage = {
                id: Date.now(),
                sender_id: user.id || user.sub,
                content: newMessage,
                created_at: new Date().toISOString(),
                is_edited: false,
                is_deleted: false
            };
            setMessages([...messages, tempMessage]);

            const contentToSend = newMessage;
            setNewMessage(''); // Hned vyčistit input

            await api.post('/messages/send', { room_id: roomId, content: contentToSend });
            loadMessages(); // Obnovit pro jistotu
        } catch (err) {
            console.error("Chyba odesílání", err);
        }
    };

    const deleteMessage = async (msgId) => {
        if (!window.confirm("Opravdu smazat zprávu?")) return;
        try {
            await api.post('/messages/delete', { message_id: msgId });
            // Lokální update
            setMessages(prev => prev.map(m =>
                m.id === msgId ? { ...m, is_deleted: true } : m
            ));
        } catch (err) {
            alert("Chyba při mazání");
        }
    };

    const startEditing = (msg) => {
        setEditingMessage(msg);
        setNewMessage(msg.content);
        setActiveMenuId(null);
        // Focus do inputu (volitelné, input má autoFocus jen při mountu)
    };

    const cancelEditing = () => {
        setEditingMessage(null);
        setNewMessage('');
    };

    // Formátování data/času
    const getDateLabel = (dateString) => {
        const date = new Date(dateString);
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        if (date.toDateString() === today.toDateString()) return "DNES";
        if (date.toDateString() === yesterday.toDateString()) return "VČERA";
        return date.toLocaleDateString();
    };

    const formatTime = (dateString) => {
        return new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <div className="chat-room">
            {/* 1. HLAVIČKA */}
            <div className="chat-header">
                <div
                    className="chat-user-info clickable"
                    onClick={() => onProfileClick(selectedUser)}
                    title="Zobrazit profil"
                >
                    <img
                        src={selectedUser.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${selectedUser.username}`}
                        alt="Avatar"
                    />
                    <div className="user-text-info">
                        <h3>{selectedUser.username}</h3>
                        <span className="status-text">{selectedUser.status || 'offline'}</span>
                    </div>
                </div>
            </div>

            {/* 2. OBLAST ZPRÁV */}
            <div className="messages-area">
                {messages.length === 0 ? (
                    <div className="empty-chat-placeholder">
                        <p>Zatím žádné zprávy.</p>
                        <small>Buďte první, kdo napíše!</small>
                    </div>
                ) : (
                    messages.map((msg, index) => {
                        const myId = user.id || user.sub;
                        const isMe = msg.sender_id === myId; // Zde pozor na typy (string vs int)

                        // Bezpečnější porovnání ID
                        const isMeSafe = String(msg.sender_id) === String(myId);

                        const showDate = index === 0 || getDateLabel(messages[index - 1].created_at) !== getDateLabel(msg.created_at);

                        return (
                            <React.Fragment key={index}>
                                {showDate && (
                                    <div className="date-separator">
                                        <span className="date-badge">{getDateLabel(msg.created_at)}</span>
                                    </div>
                                )}

                                <div className={`message-row ${isMeSafe ? 'my-message' : 'friend-message'}`}>

                                    {/* MENU TŘÍ TEČEK - JEN U MÝCH ZPRÁV A POKUD NEJSOU SMAZANÉ */}
                                    {isMeSafe && !msg.is_deleted && (
                                        <div className="message-actions">
                                            <button
                                                className="dots-btn"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setActiveMenuId(activeMenuId === msg.id ? null : msg.id);
                                                }}
                                            >
                                                ⋮
                                            </button>
                                            {activeMenuId === msg.id && (
                                                <div className="action-menu">
                                                    <button onClick={() => startEditing(msg)}>✎ Upravit</button>
                                                    <button onClick={() => deleteMessage(msg.id)} className="delete-opt">🗑 Odstranit</button>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    <div className={`message-bubble ${msg.is_deleted ? 'deleted' : ''}`}>
                                        {msg.is_deleted ? (
                                            <span className="deleted-text">
                                                🚫 <i>Tato zpráva byla odstraněna</i>
                                            </span>
                                        ) : (
                                            <span>{msg.content}</span>
                                        )}

                                        <div className="msg-meta">
                                            <span className="msg-time">{formatTime(msg.created_at)}</span>
                                            {/* Zobrazení editace */}
                                            {msg.is_edited && !msg.is_deleted && (
                                                <span className="edited-indicator" title="Zpráva byla upravena"> (upraveno)</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </React.Fragment>
                        );
                    })
                )}
                <div ref={messagesEndRef} />
            </div>

            {/* 3. INPUT */}
            <form className="chat-input-area" onSubmit={handleSend}>
                {editingMessage && (
                    <div className="editing-banner">
                        <span>Upravujete zprávu...</span>
                        <button type="button" onClick={cancelEditing}>✕ Zrušit</button>
                    </div>
                )}
                <input
                    type="text"
                    placeholder={editingMessage ? "Upravte zprávu..." : "Napište zprávu..."}
                    value={newMessage}
                    onChange={(e) => setNewMessage(e.target.value)}
                />
                <button type="submit" disabled={!newMessage.trim()}>
                    {editingMessage ? "💾" : <SendIcon />}
                </button>
            </form>
        </div>
    );
};

export default ChatWindow;