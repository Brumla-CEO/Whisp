import React, { useEffect, useState, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';

const UserList = ({ onSelectUser }) => {
    const [friends, setFriends] = useState([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [activeTab, setActiveTab] = useState('dms');
    const { api } = useContext(AuthContext);

    // Načítáme přátele místo všech uživatelů
    const fetchFriends = async () => {
        try {
            const res = await api.get('/friends');
            setFriends(res.data);
        } catch (err) {
            console.error("Chyba při načítání přátel", err);
        }
    };

    useEffect(() => {
        fetchFriends();
    }, []);

    const filteredFriends = friends.filter(u =>
        u.username.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <div className="sidebar-container">
            <div className="sidebar-header">
                <h2 className="sidebar-title" style={{color: 'white', marginBottom: '15px'}}>Chaty</h2>

                <div className="search-bar-wrapper">
                    <input
                        type="text"
                        placeholder="Hledat v přátelích..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="search-input"
                    />
                    <span className="search-icon">🔍</span>
                </div>

                <div className="sidebar-tabs">
                    <button
                        className={`tab-btn ${activeTab === 'dms' ? 'active' : ''}`}
                        onClick={() => setActiveTab('dms')}
                    >
                        PŘÍMÉ ZPRÁVY
                    </button>
                    <button
                        className={`tab-btn ${activeTab === 'groups' ? 'active' : ''}`}
                        onClick={() => setActiveTab('groups')}
                    >
                        SKUPINY
                    </button>
                </div>
            </div>

            <div className="user-list-scroll">
                {activeTab === 'dms' ? (
                    filteredFriends.length > 0 ? (
                        filteredFriends.map(u => (
                            <div
                                key={u.id}
                                className="user-item-card"
                                onClick={() => onSelectUser && onSelectUser(u)} // Příprava na otevření chatu
                            >
                                <div className="avatar-wrapper">
                                    <img
                                        src={u.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${u.username}`}
                                        alt="avatar"
                                    />
                                    {/* Zobrazujeme status (online/offline) pokud ho backend posílá */}
                                    <span className={`status-indicator ${u.status || 'offline'}`}></span>
                                </div>
                                <div className="user-info">
                                    <strong>{u.username}</strong>
                                    {/* ZMĚNA: Místo bio vypíšeme jen stav */}
                                    <span style={{ fontSize: '12px', color: u.status === 'online' ? '#4caf50' : '#888' }}>
        {u.status === 'online' ? 'Online' : 'Offline'}
    </span>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="empty-state">
                            <p>Zatím žádní přátelé.</p>
                            <small>Použijte ikonu + nahoře pro přidání.</small>
                        </div>
                    )
                ) : (
                    <div className="empty-state">
                        <p>Skupinové chaty již brzy! 🚀</p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default UserList;