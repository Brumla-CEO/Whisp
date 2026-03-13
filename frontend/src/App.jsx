import React, { useContext, useState, useEffect, useRef, useCallback } from 'react';
import { AuthContext } from './Context/AuthContext';
import Register from './Components/Register';
import Login from './Components/Login';
import UserList from './Components/UserList';
import ProfileSetup from './Components/ProfileSetup';
import AdminPanel from './Components/AdminPanel';
import FriendManager from './Components/FriendManager';
import ChatWindow from './Components/ChatWindow';
import UserProfileModal from './Components/UserProfileModal';
import { shouldCloseDirectChatOnFriendRemoval } from './utils/friendChatState';
import GroupDetailsModal from './Components/GroupDetailsModal';
import AppAlerts from './Components/AppAlerts';
import './App.css';

const SettingsIcon = () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83a2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33a1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2a2 2 0 0 1-2-2v-.09a1.65 1.65 0 0 0-1-1.51a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0a2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2a2 2 0 0 1 2-2h.09a1.65 1.65 0 0 0 1.51-1a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83a2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2a2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0a2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2a2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
);

const AddFriendIcon = () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
);

const notify = (message, type = 'info', timeout = 4200) => {
    window.dispatchEvent(new CustomEvent('app-notify', { detail: { message, type, timeout } }));
};

function App() {
    const { user, logout, api, loading } = useContext(AuthContext);

    const [showSettings, setShowSettings] = useState(false);
    const [showFriends, setShowFriends] = useState(false);
    const [viewingProfile, setViewingProfile] = useState(null);
    const [viewingGroup, setViewingGroup] = useState(null);
    const [isLogin, setIsLogin] = useState(true);

    const [selectedChatUser, setSelectedChatUser] = useState(null);
    const [activeRoomId, setActiveRoomId] = useState(null);
    const [unreadIds, setUnreadIds] = useState([]);
    const [friendRequestCount, setFriendRequestCount] = useState(0);

    const selectedChatUserRef = useRef(null);
    const activeRoomIdRef = useRef(null);
    const viewingProfileRef = useRef(null);

    const [socket, setSocket] = useState(null);
    const socketRef = useRef(null);

    useEffect(() => {
        if (!user) {
            setSelectedChatUser(null);
            setActiveRoomId(null);
            setUnreadIds([]);
            setFriendRequestCount(0);
            if (socketRef.current) {
                socketRef.current.close();
                socketRef.current = null;
            }
            setSocket(null);
        }
    }, [user]);

    useEffect(() => { selectedChatUserRef.current = selectedChatUser; }, [selectedChatUser]);
    useEffect(() => { activeRoomIdRef.current = activeRoomId; }, [activeRoomId]);
    useEffect(() => { viewingProfileRef.current = viewingProfile; }, [viewingProfile]);

    const closeDirectChatWithUser = useCallback((targetUserId, reason = 'removed') => {
        const currentSelectedChatUser = selectedChatUserRef.current;
        if (!shouldCloseDirectChatOnFriendRemoval(currentSelectedChatUser, targetUserId)) return;

        setSelectedChatUser(null);
        setActiveRoomId(null);
        setViewingGroup(null);
        setViewingProfile(null);

        if (reason === 'removed') {
            notify('Tento kontakt už není ve vašem seznamu přátel. Chat byl uzavřen.', 'warning');
        }
    }, []);

    const closeGroupChat = useCallback((targetRoomId, message) => {
        const currentActiveRoomId = activeRoomIdRef.current;
        if (!currentActiveRoomId || String(currentActiveRoomId) !== String(targetRoomId)) return;

        setSelectedChatUser(null);
        setActiveRoomId(null);
        setViewingGroup(null);

        if (message) {
            notify(message, 'warning');
        }
    }, []);

    const handleFriendRemovedImmediately = useCallback((removedUserId, reason = 'silent') => {
        closeDirectChatWithUser(removedUserId, reason);

        const currentViewingProfile = viewingProfileRef.current;
        if (currentViewingProfile && String(currentViewingProfile.id) === String(removedUserId)) {
            setViewingProfile(null);
        }

        window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
    }, [closeDirectChatWithUser]);

    useEffect(() => {
        if (loading || !user) return;

        const token = localStorage.getItem('token');
        if (!token) return;

        if (socketRef.current) {
            if (socketRef.current.readyState === WebSocket.OPEN || socketRef.current.readyState === WebSocket.CONNECTING) return;
        }

        const wsUrl = `ws://${window.location.hostname}:8080`;
        const ws = new WebSocket(wsUrl);
        socketRef.current = ws;

        const handleWebSocketMessage = (data) => {
            if (data.type === 'message:new' || data.type === 'message_update' || data.type === 'message_delete') {
                window.dispatchEvent(new CustomEvent('chat-update', { detail: data }));
            }

            if (data.type === 'notification') {
                setUnreadIds(prev => [...new Set([...prev, data.roomId || data.from])]);
                window.dispatchEvent(new CustomEvent('chat-update'));
            }

            if (data.type === 'user_status') {
                window.dispatchEvent(new CustomEvent('friend-status-change', { detail: data }));
            }

            if (data.type === 'friend_update') {
                window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));

                if (data.action === 'request_received') {
                    setFriendRequestCount(prev => prev + 1);
                    notify('Přišla vám nová žádost o přátelství.', 'info');
                }

                if (data.action === 'accepted') {
                    notify('Vaše žádost o přátelství byla přijata.', 'success');
                }

                if (data.action === 'rejected') {
                    notify('Vaše žádost o přátelství byla odmítnuta.', 'warning');
                }

                if (data.action === 'unfriended' && data.from) {
                    closeDirectChatWithUser(data.from, 'removed');
                    notify('Uživatel vás odebral z přátel.', 'warning');
                }
            }

            if (data.type === 'contact_update' || data.type === 'group_update') {
                window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));

                if (data.type === 'group_update') {
                    api.get('/rooms').then(res => {
                        const updatedRooms = Array.isArray(res.data) ? res.data : [];
                        setSelectedChatUser(prevUser => {
                            if (!prevUser) return prevUser;
                            const match = updatedRooms.find(r => String(r.id) === String(data.roomId));
                            if (match && String(prevUser.id) === String(data.roomId)) {
                                return { ...prevUser, name: match.name, avatar_url: match.avatar_url };
                            }
                            return prevUser;
                        });
                    }).catch(e => console.error('Chyba syncu skupiny', e));
                }
            }

            if (data.type === 'kicked_from_group') {
                closeGroupChat(data.roomId, `Byl jste odebrán ze skupiny "${data.groupName}".`);
                window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
            }

            if (data.type === 'group_deleted') {
                closeGroupChat(data.roomId, `Skupina "${data.groupName || 'Skupina'}" byla smazána administrátorem.`);
                window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
            }

            if (data.type === 'contact_deleted') {
                window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
                const currentSelectedChatUser = selectedChatUserRef.current;
                if (currentSelectedChatUser && String(currentSelectedChatUser.id) === String(data.userId)) {
                    setSelectedChatUser(null);
                    setActiveRoomId(null);
                    setViewingProfile(null);
                    notify('Uživatel byl odstraněn a již není dostupný.', 'warning');
                }
            }

            if (data.type === 'admin_user_deleted') {
                if (String(data.targetId) === String(user.id || user.sub)) {
                    notify('Administrátor smazal váš účet. Budete odhlášeni.', 'error', 5000);
                    window.setTimeout(() => logout(), 1800);
                }
            }
        };

        ws.onopen = () => {
            ws.send(JSON.stringify({ type: 'auth', token }));
        };

        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'auth_ok') {
                    setSocket(ws);
                    return;
                }
                if (data.type === 'auth_error') {
                    console.error('❌ WS Auth failed:', data.message);
                    ws.close();
                    return;
                }
                handleWebSocketMessage(data);
            } catch (e) {
                console.error('WS Parse Error', e);
            }
        };

        ws.onerror = (error) => {
            console.error('WS Error:', error);
        };

        ws.onclose = (e) => {
            console.log('WS Closed', e.code, e.reason);
            setSocket(null);
            socketRef.current = null;
        };

        api.get('/notifications').then(res => {
            if (Array.isArray(res.data)) setUnreadIds(res.data.map(n => n.room_id));
        }).catch(() => {});

        api.get('/friends/requests').then(res => {
            if (Array.isArray(res.data)) setFriendRequestCount(res.data.length);
        }).catch(() => {});

        return () => {
            if (socketRef.current) socketRef.current.close();
            socketRef.current = null;
            setSocket(null);
        };
    }, [user, loading, api, closeDirectChatWithUser, closeGroupChat, logout]);

    useEffect(() => {
        const syncFriendRequestCount = async () => {
            try {
                const res = await api.get('/friends/requests');
                setFriendRequestCount(Array.isArray(res.data) ? res.data.length : 0);
            } catch (e) {
                console.error('Chyba synchronizace počtu žádostí:', e);
            }
        };

        const handleFriendRequestHandled = () => {
            syncFriendRequestCount();
        };

        window.addEventListener('friend-request-handled', handleFriendRequestHandled);
        return () => window.removeEventListener('friend-request-handled', handleFriendRequestHandled);
    }, [api]);

    useEffect(() => {
        const handleLocalGroupUpdate = (e) => {
            const { roomId, name, avatar_url } = e.detail;
            if (selectedChatUserRef.current && String(selectedChatUserRef.current.id) === String(roomId)) {
                setSelectedChatUser(prev => ({ ...prev, name, avatar_url }));
            }
        };

        window.addEventListener('group-updated', handleLocalGroupUpdate);
        return () => window.removeEventListener('group-updated', handleLocalGroupUpdate);
    }, []);

    useEffect(() => {
        const handleFriendRemoved = (event) => {
            const removedUserId = event.detail?.userId;
            if (!removedUserId) return;
            closeDirectChatWithUser(removedUserId, 'removed');
        };

        window.addEventListener('friend-removed', handleFriendRemoved);
        return () => window.removeEventListener('friend-removed', handleFriendRemoved);
    }, [closeDirectChatWithUser]);

    if (loading) return <div className="loading-screen"><h2>Načítám Whisp...</h2></div>;

    if (!user) {
        return (
            <div className="app-layout">
                <AppAlerts />
                <div className="auth-wrapper">
                    <div className="auth-brand"><h1 className="logo-text">Whisp</h1><p>Vítejte v bezpečné zóně</p></div>
                    <div className="auth-card">
                        {isLogin ? <Login /> : <Register />}
                        <div className="auth-toggle">
                            <p onClick={() => setIsLogin(!isLogin)} className="clickable">{isLogin ? 'Nemáte účet? Zaregistrujte se' : 'Máte účet? Přihlaste se'}</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    const toggleSettings = () => { setShowSettings(!showSettings); setShowFriends(false); };
    const toggleFriends = () => { setShowFriends(!showFriends); setShowSettings(false); };

    const handleUserSelect = async (friend) => {
        setSelectedChatUser(friend);
        setActiveRoomId(null);
        setShowSettings(false);
        setUnreadIds(prev => prev.filter(id => id !== friend.id && id !== friend.room_id));

        if (friend.type === 'group') {
            setActiveRoomId(friend.id);
        } else {
            try {
                const res = await api.post('/chat/open', { target_id: friend.id });
                if (res.data.room_id) setActiveRoomId(res.data.room_id);
            } catch (err) {
                if (err.response?.status === 404) {
                    notify('Tento uživatel již neexistuje.', 'warning');
                    window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
                } else if (err.response?.status === 403) {
                    notify('Tento soukromý chat už není dostupný.', 'warning');
                    setSelectedChatUser(null);
                    setActiveRoomId(null);
                    window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
                } else {
                    console.error('Chyba chatu', err);
                }
            }
        }
    };

    const handleProfileClick = (entity) => {
        if (entity.type === 'group') setViewingGroup(entity);
        else setViewingProfile(entity);
    };

    const handleLeaveGroup = () => {
        setSelectedChatUser(null);
        setActiveRoomId(null);
        setViewingGroup(null);
        window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
    };

    if (user.role === 'admin') {
        return (
            <>
                <AppAlerts />
                <AdminPanel socket={socket} />
            </>
        );
    }

    return (
        <div className="app-layout">
            <AppAlerts />
            <header>
                <div className="header-left">
                    <h1 className="app-logo">Whisp</h1>
                    <span className="user-tag clickable" onClick={() => setViewingProfile(user)}>@{user.username}</span>
                </div>
                <div className="header-right">
                    <button onClick={toggleFriends} className={`icon-btn ${showFriends ? 'active' : ''}`} title="Přátelé">
                        <AddFriendIcon />
                        {friendRequestCount > 0 && <span className="notification-dot"></span>}
                    </button>
                    <button onClick={toggleSettings} className={`icon-btn settings-btn ${showSettings ? 'active' : ''}`} title="Nastavení"><SettingsIcon /></button>
                    <button onClick={logout} className="logout-btn">Odhlásit</button>
                </div>
            </header>

            <main className="main-content">
                <aside className="sidebar">
                    <UserList onSelectUser={handleUserSelect} unreadIds={unreadIds} socket={socket} />
                </aside>

                <section className="chat-window">
                    {showSettings ? (
                        <div className="settings-view">
                            <button onClick={() => setShowSettings(false)} className="close-btn-text">&larr; Zpět</button>
                            <ProfileSetup socket={socket} />
                        </div>
                    ) : (
                        selectedChatUser && activeRoomId ? (
                            <ChatWindow selectedUser={selectedChatUser} roomId={activeRoomId} onProfileClick={handleProfileClick} socket={socket} />
                        ) : (
                            <div className="welcome-hero">
                                <h2>Vítej zpět, {user.username}!</h2>
                                <p>Vyber si konverzaci vlevo.</p>
                            </div>
                        )
                    )}
                </section>
            </main>

            {showFriends && (
                <FriendManager socket={socket} onClose={() => setShowFriends(false)} onViewProfile={handleProfileClick} setFriendRequestCount={setFriendRequestCount} />
            )}

            {viewingProfile && (
                <UserProfileModal user={viewingProfile} onClose={() => setViewingProfile(null)} socket={socket} onFriendRemoved={handleFriendRemovedImmediately} />
            )}
            {viewingGroup && <GroupDetailsModal group={viewingGroup} onClose={() => setViewingGroup(null)} onLeaveGroup={handleLeaveGroup} socket={socket} />}
        </div>
    );
}

export default App;
