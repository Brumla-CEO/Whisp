import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';

const FriendManager = ({ onClose, onViewProfile }) => {
    const { api, user } = useContext(AuthContext);
    const [activeTab, setActiveTab] = useState('search'); // 'search', 'requests'

    // Stavy pro hledání
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [searchMessage, setSearchMessage] = useState('');

    // Stavy pro žádosti
    const [pendingRequests, setPendingRequests] = useState([]);

    // Načtení žádostí při otevření záložky 'requests'
    useEffect(() => {
        if (activeTab === 'requests') {
            loadRequests();
        }
    }, [activeTab]);

    const loadRequests = async () => {
        try {
            const res = await api.get('/friends/requests');
            setPendingRequests(res.data);
        } catch (err) {
            console.error("Chyba při načítání žádostí", err);
        }
    };

    const handleSearch = async (e) => {
        e.preventDefault();
        if (searchQuery.length < 1) return;

        try {
            const res = await api.get(`/friends/search?q=${searchQuery}`);
            // Filtrujeme, abychom nehledali sami sebe
            const filtered = res.data.filter(u => u.id !== user.id);
            setSearchResults(filtered);
            if (filtered.length === 0) setSearchMessage('Nikdo nenalezen.');
            else setSearchMessage('');
        } catch (err) {
            setSearchMessage('Chyba při hledání.');
        }
    };

    const sendRequest = async (targetId) => {
        try {
            await api.post('/friends/add', { target_id: targetId });
            alert('Žádost odeslána!');
            // Odstraníme z výsledků, aby to uživatele nemátlo
            setSearchResults(prev => prev.filter(u => u.id !== targetId));
        } catch (err) {
            alert(err.response?.data?.message || 'Chyba při odesílání.');
        }
    };

    const acceptRequest = async (requestId) => {
        try {
            await api.post('/friends/accept', { request_id: requestId });
            // Odstranit ze seznamu čekajících
            setPendingRequests(prev => prev.filter(r => r.request_id !== requestId));
            alert('Přátelství přijato!');
            window.location.reload(); // Pro obnovení sidebaru
        } catch (err) {
            alert('Chyba při přijímání.');
        }
    };

    return (
        <div className="modal-overlay">
            <div className="modal-content">
                <div className="modal-header">
                    <h3>Správce přátel</h3>
                    <button onClick={onClose} className="close-btn-icon">✕</button>
                </div>

                <div className="modal-tabs">
                    <button
                        className={`tab-btn ${activeTab === 'search' ? 'active' : ''}`}
                        onClick={() => setActiveTab('search')}
                    >
                        🔍 Hledat nové
                    </button>
                    <button
                        className={`tab-btn ${activeTab === 'requests' ? 'active' : ''}`}
                        onClick={() => setActiveTab('requests')}
                    >
                        📩 Žádosti {pendingRequests.length > 0 && <span className="badge-count">{pendingRequests.length}</span>}
                    </button>
                </div>

                <div className="modal-body">
                    {/* ZÁLOŽKA HLEDÁNÍ */}
                    {activeTab === 'search' && (
                        <div className="search-section">
                            <form onSubmit={handleSearch} className="search-form">
                                <input
                                    type="text"
                                    placeholder="Zadejte uživatelské jméno..."
                                    value={searchQuery}
                                    onChange={e => setSearchQuery(e.target.value)}
                                />
                                <button type="submit">Hledat</button>
                            </form>

                            {searchMessage && <p className="status-msg">{searchMessage}</p>}

                            <div className="results-list">
                                {searchResults.map(u => (
                                    <div key={u.id} className="user-card-row">
                                        <img
                                            src={u.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${u.username}`}
                                            alt="Avatar"
                                            className="clickable-avatar"
                                            onClick={() => onViewProfile && onViewProfile(u)}
                                        />
                                        <div className="user-info-col">
                                            <strong>{u.username}</strong>
                                        </div>
                                        <button onClick={() => sendRequest(u.id)} className="add-btn">
                                            Poslat žádost
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* ZÁLOŽKA ŽÁDOSTI */}
                    {activeTab === 'requests' && (
                        <div className="requests-section">
                            {pendingRequests.length === 0 ? (
                                <p className="empty-msg">Nemáte žádné nové žádosti.</p>
                            ) : (
                                pendingRequests.map(req => (
                                    <div key={req.request_id} className="user-card-row">
                                        <img
                                            src={req.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${req.username}`}
                                            alt="Avatar"
                                            className="clickable-avatar"
                                            onClick={() => onViewProfile && onViewProfile(req)}
                                        />
                                        <div className="user-info-col">
                                            <strong>{req.username}</strong>
                                            <span className="timestamp">odesláno {new Date(req.created_at).toLocaleDateString()}</span>
                                        </div>
                                        <div className="actions">
                                            <button onClick={() => acceptRequest(req.request_id)} className="accept-btn">
                                                ✔ Přijmout
                                            </button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default FriendManager;