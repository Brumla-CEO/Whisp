import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../Context/AuthContext';
import CreateGroupModal from './CreateGroupModal';

const UserList = ({ onSelectUser, unreadIds = [], socket }) => {
    const { api } = useContext(AuthContext);
    const [allItems, setAllItems] = useState([]);
    const [search, setSearch] = useState("");
    const [activeTab, setActiveTab] = useState('all');
    const [showCreateModal, setShowCreateModal] = useState(false);

    const fetchData = async () => {
        try {
            // Načteme přátele a místnosti (rooms obsahuje i DM i Groups)
            const [friendsRes, roomsRes] = await Promise.all([
                api.get('/friends').catch(() => ({ data: [] })),
                api.get('/rooms').catch(() => ({ data: [] }))
            ]);

            const friends = Array.isArray(friendsRes.data) ? friendsRes.data : [];
            const rooms = Array.isArray(roomsRes.data) ? roomsRes.data : [];

            // Rozdělení rooms na skupiny a DMs
            const groups = rooms.filter(r => r.type === 'group');
            const dms = rooms.filter(r => r.type !== 'group');

            // Spojení Přátel s jejich DM historií
            const mergedFriends = friends.map(friend => {
                // Najdeme, jestli už máme otevřený chat s tímto přítelem
                const dmInfo = dms.find(r => r.name === friend.username) || {};

                return {
                    ...friend,
                    type: 'dm',
                    // Data z místnosti mají přednost (poslední zpráva, počet nepřečtených)
                    last_message: dmInfo.last_message || null,
                    unread_count: dmInfo.unread_count || 0,
                    room_id: dmInfo.id || null
                };
            });

            // Spojíme skupiny a přátele do jednoho seznamu
            const combined = [...groups, ...mergedFriends].sort((a, b) => {
                // Řazení: primárně podle nepřečtených, pak podle času?
                // Zde jednoduché řazení, aby nepřečtené byly nahoře
                return (b.unread_count || 0) - (a.unread_count || 0);
            });

            setAllItems(combined);
        } catch (err) { console.error("Chyba načítání dat:", err); }
    };

    useEffect(() => {
        fetchData();

        // Listener pro změnu statusu (online/offline)
        const handleStatusChange = (event) => {
            const data = event.detail;
            if (data.refresh) { fetchData(); return; }
            if (data.userId && data.status) {
                setAllItems(prev => prev.map(item => String(item.id) === String(data.userId) ? { ...item, status: data.status } : item));
            }
        };
        window.addEventListener('friend-status-change', handleStatusChange);

        // Listener pro nové zprávy (aby se aktualizoval last_message)
        const handleChatUpdate = () => fetchData();
        window.addEventListener('chat-update', handleChatUpdate);

        return () => {
            window.removeEventListener('friend-status-change', handleStatusChange);
            window.removeEventListener('chat-update', handleChatUpdate);
        };
    }, [api]);

    const filteredItems = allItems.filter(item => {
        const name = item.name || item.username || "";
        if (!name.toLowerCase().includes(search.toLowerCase())) return false;
        if (activeTab === 'all') return true;
        if (activeTab === 'online') return item.type === 'dm' && item.status === 'online';
        if (activeTab === 'groups') return item.type === 'group';
        return true;
    });

    return (
        <div className="sidebar-container">
            <div className="sidebar-header">
                <div className="header-top"><h2 className="sidebar-title">Zprávy</h2><button className="add-group-btn" onClick={() => setShowCreateModal(true)} title="Vytvořit skupinu">+</button></div>
                <div className="search-bar-wrapper"><span className="search-icon">🔍</span><input type="text" className="search-input" placeholder="Hledat..." value={search} onChange={(e) => setSearch(e.target.value)} /></div>
                <div className="sidebar-tabs">
                    <button className={`tab-btn ${activeTab === 'all' ? 'active' : ''}`} onClick={() => setActiveTab('all')}>VŠE</button>
                    <button className={`tab-btn ${activeTab === 'online' ? 'active' : ''}`} onClick={() => setActiveTab('online')}>ONLINE</button>
                    <button className={`tab-btn ${activeTab === 'groups' ? 'active' : ''}`} onClick={() => setActiveTab('groups')}>SKUPINY</button>
                </div>
            </div>
            <div className="user-list-scroll">
                {filteredItems.length === 0 ? <div className="empty-state">{activeTab === 'groups' ? "Žádné skupiny" : "Nic nalezeno"}</div> :
                    filteredItems.map(item => {
                        const isGroup = item.type === 'group';
                        let name = item.name || item.username;

                        if (!isGroup && name && name.startsWith('deleted_')) {
                            name = "Smazaný uživatel";
                        }
                        const avatar = item.avatar_url || (isGroup ? `https://api.dicebear.com/7.x/initials/svg?seed=${name}` : `https://api.dicebear.com/7.x/avataaars/svg?seed=${name}`);

                        // Zkontrolujeme nepřečtené (buď z API nebo z živého Socketu v App.jsx)
                        const hasUnread = (item.unread_count > 0) || (unreadIds.includes(item.id) || unreadIds.includes(item.room_id));

                        return (
                            <div key={item.id} className="user-item-card" onClick={() => onSelectUser(item)}>
                                <div className="avatar-wrapper">
                                    <img src={avatar} alt="Avatar" className={isGroup ? 'group-avatar' : ''} />
                                    {!isGroup && <span className={`status-indicator ${item.status === 'online' ? 'online' : 'offline'}`}></span>}
                                    {hasUnread && <span className="notification-dot"></span>}
                                </div>
                                <div className="user-info">
                                    <div className="room-name-row"><span className="user-name" style={{fontWeight: hasUnread ? 'bold' : 'normal', color: hasUnread ? '#fff' : ''}}>{name}</span></div>
                                    <span className="truncate-bio">
                                        {hasUnread ? <span style={{color: '#bb86fc', fontWeight: 'bold'}}>Nová zpráva!</span> :
                                            <span style={{ color: (!isGroup && item.status === 'online') ? '#4caf50' : '#888' }}>{item.last_message ? (item.last_message.length > 25 ? item.last_message.substring(0, 25) + '...' : item.last_message) : (isGroup ? "Skupina" : (item.status === 'online' ? "Online" : "Offline"))}</span>}
                                    </span>
                                </div>
                            </div>
                        );
                    })
                }
            </div>
            {showCreateModal && <CreateGroupModal onClose={() => setShowCreateModal(false)} onGroupCreated={fetchData} socket={socket} />}

        </div>
    );
};

export default UserList;