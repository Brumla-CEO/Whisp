import React, { useEffect, useState, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';

const UserList = () => {
    const [users, setUsers] = useState([]);
    const [searchTerm, setSearchTerm] = useState(''); // Logika filtrování
    const [activeTab, setActiveTab] = useState('dms'); // Logika záložek
    const { api, user: currentUser } = useContext(AuthContext);

    const fetchUsers = async () => {
        try {
            const res = await api.get('/users');
            // Filtrujeme, abychom neviděli sami sebe
            setUsers(res.data.filter(u => u.id !== currentUser.sub));
        } catch (err) {
            console.error("Chyba při načítání uživatelů", err);
        }
    };

    useEffect(() => {
        fetchUsers();
    }, []);

    //  filtrování uživatelů podle jména
    const filteredUsers = users.filter(u =>
        u.username.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <div className="sidebar-container">
            <div className="sidebar-header">
                <h2 className="sidebar-title" style={{color: 'white', marginBottom: '15px'}}>Chaty</h2>

                <div className="search-bar-wrapper">
                    <input
                        type="text"
                        placeholder="Hledat chaty..."
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

            {

            }
            <div className="user-list-scroll">
                {activeTab === 'dms' ? (
                    filteredUsers.length > 0 ? (
                        filteredUsers.map(u => (
                            <div key={u.id} className="user-item-card">
                                <div className="avatar-wrapper">
                                    <img
                                        src={u.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${u.username}`}
                                        alt="avatar"
                                    />
                                    <span className={`status-indicator ${u.status}`}></span>
                                </div>
                                <div className="user-info">
                                    <strong>{u.username}</strong>
                                    <span className="user-status-text">{u.status}</span>
                                </div>
                            </div>
                        ))
                    ) : (
                        <p className="empty-state">Nikdo nebyl nalezen...</p>
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