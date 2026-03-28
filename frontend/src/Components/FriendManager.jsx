import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../Context/AuthContext';

const notify = (message, type = 'info', timeout = 4200) => {
    window.dispatchEvent(new CustomEvent('app-notify', { detail: { message, type, timeout } }));
};

const FriendManager = ({ onClose, onViewProfile, socket, setFriendRequestCount }) => {
    const { api, user } = useContext(AuthContext);
    const [activeTab, setActiveTab] = useState('search');
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [searchMessage, setSearchMessage] = useState('');

    const [pendingRequests, setPendingRequests] = useState([]);
    const [sentRequests, setSentRequests] = useState([]);

    useEffect(() => { loadRequests(); }, [activeTab]);

    useEffect(() => {
        const handleStatusChange = (event) => {
            if (event.detail && event.detail.refresh && activeTab === 'requests') loadRequests();
        };
        window.addEventListener('friend-status-change', handleStatusChange);
        return () => window.removeEventListener('friend-status-change', handleStatusChange);
    }, [activeTab]);

    const loadRequests = async () => {
        try {
            const res = await api.get('/friends/requests');
            setPendingRequests(Array.isArray(res.data) ? res.data : []);
        } catch (err) { console.error(err); }
    };

    const handleSearch = async (e) => {
        e.preventDefault();
        if (searchQuery.length < 1) return;
        setSearchMessage('');
        try {
            const res = await api.get(`/friends/search?q=${searchQuery}`);
            const users = Array.isArray(res.data) ? res.data : [];
            setSearchResults(users.filter(u => u.id !== user.id));
            if (users.length === 0) setSearchMessage('Nikdo nenalezen.');
        } catch (err) { setSearchMessage('Chyba při hledání.'); }
    };

    const sendRequest = async (targetId) => {
        try {
            await api.post('/friends/add', { target_id: targetId });
            setSentRequests(prev => [...prev, targetId]);
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({ type: 'friend_action', targetId: targetId, action: 'request_received' }));
            }
        } catch (err) { notify('Chyba odesílání žádosti. Možná už existuje.', 'error'); }
    };

    const acceptRequest = async (requestId, senderId) => {
        try {
            await api.post('/friends/accept', { request_id: requestId });
            setPendingRequests(prev => prev.filter(r => r.request_id !== requestId));
            if (setFriendRequestCount) setFriendRequestCount(prev => Math.max(0, prev - 1));

            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({ type: 'friend_action', targetId: senderId, action: 'accepted' }));
            }
            window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
            window.dispatchEvent(new CustomEvent('friend-request-handled'));
            notify('Přátelství přijato.', 'success');
        } catch (err) { notify('Chyba při přijímání žádosti.', 'error'); }
    };

    const rejectRequest = async (requestId, senderId) => {
        try {
            await api.post('/friends/reject', { request_id: requestId });
            setPendingRequests(prev => prev.filter(r => r.request_id !== requestId));
            if (setFriendRequestCount) setFriendRequestCount(prev => Math.max(0, prev - 1));
            if (socket && socket.readyState === WebSocket.OPEN && senderId) {
                socket.send(JSON.stringify({ type: 'friend_action', targetId: senderId, action: 'rejected' }));
            }
            window.dispatchEvent(new CustomEvent('friend-request-handled'));
            window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
            notify('Žádost byla odmítnuta.', 'info');
        } catch (err) { notify('Chyba při odmítání žádosti.', 'error'); }
    };

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-content" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h3>Správce přátel</h3>
                    <button onClick={(e) => { e.stopPropagation(); onClose(); }} className="close-btn-icon" style={{ position: 'relative', zIndex: 10000, cursor: 'pointer', width: 'auto' }}>✕</button>
                </div>

                <div className="modal-tabs">
                    <button className={activeTab === 'search' ? 'active' : ''} onClick={() => setActiveTab('search')}>🔍 Hledat</button>
                    <button className={activeTab === 'requests' ? 'active' : ''} onClick={() => setActiveTab('requests')}>
                        📩 Žádosti {pendingRequests.length > 0 && <span className="badge-count">{pendingRequests.length}</span>}
                    </button>
                </div>

                <div className="modal-body">
                    {activeTab === 'search' && (
                        <div className="search-section">
                            <form onSubmit={handleSearch} className="search-form">
                                <input type="text" value={searchQuery} onChange={e => setSearchQuery(e.target.value)} placeholder="Zadej jméno uživatele..." autoFocus />
                                <button type="submit">Hledat</button>
                            </form>
                            {searchMessage && <p className="search-message">{searchMessage}</p>}
                            <div className="results-list">
                                {searchResults.map(u => {
                                    const isSent = sentRequests.includes(u.id);
                                    const isAdmin = u.role === 'admin';
                                    return (
                                        <div key={u.id} className="user-card-row">
                                            <img src={u.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${u.username}`} alt="Av" className="clickable-avatar" onClick={() => onViewProfile(u)} />
                                            <div className="user-info-col">
                                                <strong>{u.username} {isAdmin && <span className="admin-badge-text">ADMIN</span>}</strong>
                                                <span>{isAdmin ? 'Nelze přidat' : (isSent ? 'Žádost odeslána' : 'Uživatel')}</span>
                                            </div>
                                            {!isAdmin && (
                                                <button onClick={() => sendRequest(u.id)} className="add-btn" disabled={isSent}>{isSent ? 'Odesláno ✔' : 'Poslat žádost'}</button>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                    {activeTab === 'requests' && (
                        <div className="requests-section">
                            {pendingRequests.length === 0 && <p className="search-message">Žádné nové žádosti.</p>}
                            {pendingRequests.map(req => (
                                <div key={req.request_id} className="user-card-row">
                                    <img src={req.avatar_url} alt="Av" />
                                    <div className="user-info-col"><strong>{req.username}</strong><span>{new Date(req.created_at).toLocaleDateString()}</span></div>
                                    <div className="actions-row">
                                        <button onClick={() => acceptRequest(req.request_id, req.requester_id || req.id)} className="accept-btn">✔ Přijmout</button>
                                        <button onClick={() => rejectRequest(req.request_id, req.requester_id || req.id)} className="reject-btn">✕ Odmítnout</button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default FriendManager;