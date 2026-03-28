import React, { useEffect, useState, useContext } from 'react';
import { AuthContext } from '../Context/AuthContext';

const notify = (message, type = 'info', timeout = 4200) => {
    window.dispatchEvent(new CustomEvent('app-notify', { detail: { message, type, timeout } }));
};

const normalizeDisplayName = (name) => {
    if (!name) return 'Neznámý uživatel';
    return String(name).startsWith('deleted_') ? 'Smazaný uživatel' : name;
};

const AdminPanel = ({ socket }) => {
    const { api, logout } = useContext(AuthContext);
    const [activeTab, setActiveTab] = useState('dashboard');

    const [stats, setStats] = useState(null);
    const [users, setUsers] = useState([]);
    const [rooms, setRooms] = useState([]);
    const [logs, setLogs] = useState([]);

    const [logFilter, setLogFilter] = useState('ALL');
    const [userSearch, setUserSearch] = useState('');

    const [chatPreview, setChatPreview] = useState(null);
    const [userDetail, setUserDetail] = useState(null);
    const [roomDetail, setRoomDetail] = useState(null);
    const [showCreateAdmin, setShowCreateAdmin] = useState(false);
    const [newAdminForm, setNewAdminForm] = useState({ username: '', email: '', password: '' });

    const loadData = async () => {
        try {
            if (activeTab === 'dashboard') { const res = await api.get('/admin/dashboard'); setStats(res.data || null); }
            if (activeTab === 'users') { const res = await api.get('/admin/users'); if(Array.isArray(res.data)) setUsers(res.data); }
            if (activeTab === 'rooms') { const res = await api.get('/admin/rooms'); if(Array.isArray(res.data)) setRooms(res.data); }
            if (activeTab === 'logs') { const res = await api.get('/admin/logs'); if(Array.isArray(res.data)) setLogs(res.data); }
        } catch (e) {
            console.error("Chyba načítání dat:", e);
        }
    };

    useEffect(() => { loadData(); }, [activeTab]);

    useEffect(() => {
        const interval = setInterval(() => { loadData(); }, 4000);
        return () => clearInterval(interval);
    }, [activeTab]);

    const getActionBadgeClass = (action) => {
        if (!action) return 'badge-default';
        const act = action.toUpperCase();
        if (act.includes('LOGIN') || act.includes('LOGOUT')) return 'badge-auth'; // Zelená
        if (act.includes('DELETE') || act.includes('KICK') || act.includes('ACCOUNT_DELETED')) return 'badge-danger'; // Červená
        if (act.includes('REGISTER') || act.includes('CREATED')) return 'badge-success'; // Fialová
        return 'badge-default';
    };

    const handleDeleteUser = async (id, name) => {
        if(confirm(`Opravdu smazat uživatele ${name}? Tato akce je nevratná.`)) {
            try {
                await api.post('/admin/users/delete', { user_id: id });

                if (socket && socket.readyState === WebSocket.OPEN) {
                    socket.send(JSON.stringify({ type: 'admin_user_deleted', targetId: id, deletedUsername: name }));
                    socket.send(JSON.stringify({ type: 'contact_deleted', userId: id }));
                }

                notify(`Uživatel ${name} byl smazán.`, 'success');
                loadData();
            } catch(e) {
                notify('Chyba: ' + (e.response?.data?.message || 'Nelze smazat uživatele.'), 'error');
            }
        }
    };

    const handleDeleteRoom = async (id, name) => {
        if(confirm(`Smazat místnost ${name}?`)) {
            try {
                let memberIds = [];
                try {
                    const detailRes = await api.get(`/admin/rooms/detail?room_id=${id}`);
                    memberIds = Array.isArray(detailRes.data?.members) ? detailRes.data.members.map(m => m.id) : [];
                } catch (detailError) {
                    console.warn('Nepodařilo se načíst členy místnosti pro realtime notifikaci.', detailError);
                }

                await api.post('/admin/rooms/delete', { room_id: id });

                if (socket && socket.readyState === WebSocket.OPEN) {
                    socket.send(JSON.stringify({ type: 'admin_room_deleted', roomId: id, roomName: name, memberIds }));
                }

                notify(`Místnost ${name} byla smazána.`, 'success');
                loadData();
            } catch(e){
                notify('Chyba při mazání místnosti.', 'error');
            }
        }
    };

    const handleCreateAdmin = async (e) => {
        e.preventDefault();
        if(!confirm("Opravdu vytvořit nového administrátora?")) return;
        try {
            await api.post('/admin/create-admin', newAdminForm);
            notify('Administrátor byl úspěšně vytvořen.', 'success');
            setShowCreateAdmin(false);
            setNewAdminForm({ username: '', email: '', password: '' });
            loadData();
        } catch(e) {
            notify('Chyba: ' + (e.response?.data?.message || 'Neznámá chyba'), 'error');
        }
    };

    const handlePeekRoom = async (room) => {
        try { const res = await api.get(`/admin/chat/history?room_id=${room.id}`); setChatPreview({ name: room.name, messages: res.data }); } catch(e){}
    };

    const handleRoomDetail = async (room) => {
        try { const res = await api.get(`/admin/rooms/detail?room_id=${room.id}`); setRoomDetail({ room: room, members: res.data.members }); } catch(e){}
    };

    const handleViewUser = async (user) => {
        try { const res = await api.get(`/admin/users/detail?user_id=${user.id}`); setUserDetail({ user: user, history: res.data.logs }); } catch(e){}
    };

    const filteredUsers = users.filter(u => u.username.toLowerCase().includes(userSearch.toLowerCase()));

    const filteredLogs = logs.filter(log => {
        if (logFilter === 'ALL') return true;
        if (logFilter === 'LOGIN') return log.action.includes('LOGIN') || log.action.includes('LOGOUT');
        if (logFilter === 'DELETE') return log.action.includes('DELETE') || log.action.includes('ACCOUNT');
        if (logFilter === 'REGISTER') return log.action.includes('REGISTER') || log.action.includes('CREATED');
        if (logFilter === 'ADMIN') return log.action.includes('ADMIN');
        return true;
    });

    return (
        <div className="admin-layout-full">
            <aside className="admin-sidebar">
                <div className="admin-logo">🛡️ Whisp Admin</div>
                <nav>
                    <button className={activeTab === 'dashboard' ? 'active' : ''} onClick={() => setActiveTab('dashboard')}>📊 Přehled</button>
                    <button className={activeTab === 'users' ? 'active' : ''} onClick={() => setActiveTab('users')}>👥 Uživatelé</button>
                    <button className={activeTab === 'rooms' ? 'active' : ''} onClick={() => setActiveTab('rooms')}>💬 Místnosti</button>
                    <button className={activeTab === 'logs' ? 'active' : ''} onClick={() => setActiveTab('logs')}>📜 Logy</button>
                </nav>
                <button onClick={logout} className="admin-logout">Odhlásit</button>
            </aside>

            <main className="admin-content">
                {activeTab === 'dashboard' && stats && (
                    <div className="dashboard-grid">
                        <div className="stat-card"><h3>Uživatelé</h3><p>{stats?.counts?.users}</p></div>
                        <div className="stat-card online"><h3>Online</h3><p>{stats?.counts?.online}</p></div>
                        <div className="stat-card"><h3>Místnosti</h3><p>{stats?.counts?.rooms}</p></div>
                        <div className="stat-card"><h3>Zprávy</h3><p>{stats?.counts?.messages}</p></div>

                        <div className="recent-logs-panel" style={{gridColumn: '1 / -1'}}>
                            <div className="table-header-row" style={{marginBottom:'10px'}}>
                                <h3 style={{margin:0}}>Poslední aktivita</h3>
                            </div>

                            <div className="table-responsive">
                                <table>
                                    <thead>
                                    <tr>
                                        <th className="log-col-time">Čas</th>
                                        <th className="log-col-user">Uživatel</th>
                                        <th className="log-col-action">Akce</th>
                                        <th className="log-col-details">Detail</th>
                                        <th className="log-col-ip">IP</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    {stats.recent_logs && stats.recent_logs.map(log => (
                                        <tr key={log.id}>
                                            <td>{new Date(log.timestamp).toLocaleString()}</td>

                                            <td>
                                                <span style={{fontWeight:'500', color:'#fff'}}>
                                                    {log.username || 'System'}
                                                </span>
                                            </td>

                                            <td>
                                                <span className={`log-badge ${getActionBadgeClass(log.action)}`}>
                                                    {log.action}
                                                </span>
                                            </td>

                                            <td style={{color: '#aaa', fontSize: '0.9rem'}}>
                                                {log.details ? log.details : '-'}
                                            </td>

                                            <td style={{fontFamily:'monospace', fontSize:'0.85rem'}}>
                                                {log.ip_address}
                                            </td>
                                        </tr>
                                    ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'users' && (
                    <div className="table-container">
                        <div className="table-header-row">
                            <h2>Správa uživatelů</h2>
                            <div className="header-actions">
                                <input
                                    type="text"
                                    placeholder="Hledat uživatele..."
                                    value={userSearch}
                                    onChange={(e) => setUserSearch(e.target.value)}
                                    className="admin-search-input"
                                />
                                <button onClick={() => setShowCreateAdmin(true)} className="add-admin-btn">+ Nový Admin</button>
                            </div>
                        </div>
                        <div className="table-responsive">
                            <table>
                                <thead><tr><th>Uživatel</th><th>Email</th><th>Role</th><th>Status</th><th>Akce</th></tr></thead>
                                <tbody>
                                {filteredUsers.map(u => (
                                    <tr key={u.id}>
                                        <td style={{display:'flex', alignItems:'center', gap:'10px'}}>
                                            {u.role_name !== 'admin' && (
                                                <img src={u.avatar_url} className="table-avatar" alt=""/>
                                            )}
                                            {u.role_name === 'admin' && <span style={{fontSize:'1.5rem'}}>🛡️</span>}

                                            <span style={{fontWeight: u.role_name === 'admin' ? 'bold' : 'normal', color: u.role_name === 'admin' ? '#bb86fc' : 'white'}}>
                                                {normalizeDisplayName(u.username)}
                                            </span>
                                        </td>
                                        <td>{u.email}</td>
                                        <td><span className={`role-badge ${u.role_name}`}>{u.role_name}</span></td>
                                        <td><span className={`status-dot ${u.status}`}></span> {u.status}</td>
                                        <td>
                                            <button onClick={() => handleViewUser(u)} className="btn-small">Detail</button>

                                            <button
                                                onClick={() => handleDeleteUser(u.id, u.username)}
                                                className="btn-danger-small"
                                            >
                                                Smazat
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {activeTab === 'rooms' && (
                    <div className="table-container">
                        <h2>Správa místností</h2>
                        <div className="table-responsive">
                            <table>
                                <thead><tr><th>Název</th><th>Typ</th><th>Vlastník</th><th>Zpráv</th><th>Akce</th></tr></thead>
                                <tbody>
                                {rooms.map(r => (
                                    <tr key={r.id}>
                                        <td>{r.name || <span style={{color:'#666', fontStyle:'italic'}}>Soukromá konverzace</span>}</td>
                                        <td>{r.type === 'dm' ? 'DM' : 'Skupina'}</td>
                                        <td>{r.owner_name || '-'}</td>
                                        <td>{r.msg_count}</td>
                                        <td style={{whiteSpace:'nowrap'}}>
                                            <button onClick={() => handleRoomDetail(r)} className="btn-small">Detail</button>
                                            <button onClick={() => handlePeekRoom(r)} className="btn-small">👁️ Chat</button>
                                            <button onClick={() => handleDeleteRoom(r.id, r.name)} className="btn-danger-small">Smazat</button>
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {activeTab === 'logs' && (
                    <div className="table-container">
                        <div className="table-header-row">
                            <h2>Systémové logy</h2>
                            <select value={logFilter} onChange={(e) => setLogFilter(e.target.value)} className="log-filter-select">
                                <option value="ALL">Všechny akce</option>
                                <option value="LOGIN">Auth (Login/Logout)</option>
                                <option value="REGISTER">Registrace</option>
                                <option value="ADMIN">Admin akce</option>
                                <option value="DELETE">Mazání</option>
                            </select>
                        </div>
                        <div className="table-responsive">
                            <table>
                                <thead>
                                <tr>
                                    <th className="log-col-time">Čas</th>
                                    <th className="log-col-user">Uživatel</th>
                                    <th className="log-col-action">Akce</th>
                                    <th className="log-col-details">Detail</th>
                                    <th className="log-col-ip">IP</th>
                                </tr>
                                </thead>
                                <tbody>
                                {filteredLogs.map(l => (
                                    <tr key={l.id}>
                                        <td>{new Date(l.timestamp).toLocaleString()}</td>

                                        <td>
                                            <span style={{fontWeight:'500', color:'#fff'}}>
                                                {l.username || 'System'}
                                            </span>
                                        </td>

                                        <td>
                                            <span className={`log-badge ${getActionBadgeClass(l.action)}`}>
                                                {l.action}
                                            </span>
                                        </td>

                                        <td style={{color: '#aaa', fontSize: '0.9rem'}}>
                                            {l.details ? l.details : '-'}
                                        </td>

                                        <td style={{fontFamily:'monospace', fontSize:'0.85rem'}}>
                                            {l.ip_address}
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </main>

            {roomDetail && (
                <div className="modal-overlay" onClick={() => setRoomDetail(null)}>
                    <div className="modal-content" onClick={e => e.stopPropagation()}>
                        <h3>Detail: {roomDetail.room.name || 'Soukromá konverzace'}</h3>
                        <p style={{color:'#888', marginBottom:'15px'}}>Typ: {roomDetail.room.type}</p>
                        <h4>Členové ({roomDetail.members.length})</h4>
                        <ul className="friends-selection-list" style={{border:'none', padding:0}}>
                            {roomDetail.members.map(m => (
                                <li key={m.id} className="friend-select-item" style={{cursor:'default'}}>
                                    <img src={m.avatar_url} alt="" />
                                    <span>{normalizeDisplayName(m.username)}</span>
                                    {m.role === 'admin' && <span className="role-badge admin" style={{marginLeft:'auto'}}>Admin</span>}
                                </li>
                            ))}
                        </ul>
                        <button onClick={() => setRoomDetail(null)} className="btn-cancel" style={{marginTop:'10px'}}>Zavřít</button>
                    </div>
                </div>
            )}

            {userDetail && (
                <div className="modal-overlay" onClick={() => setUserDetail(null)}>
                    <div className="modal-content" onClick={e => e.stopPropagation()}>
                        <h3>Detail: {normalizeDisplayName(userDetail.user.username)}</h3>
                        <div style={{display:'flex', gap:'20px', marginBottom:'20px'}}>
                            {userDetail.user.role_name !== 'admin' ? (
                                <img src={userDetail.user.avatar_url} style={{width:'80px', height:'80px', borderRadius:'50%'}} alt=""/>
                            ) : (
                                <div style={{width:'80px', height:'80px', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'3rem'}}>🛡️</div>
                            )}
                            <div>
                                <p><strong>Email:</strong> {userDetail.user.email}</p>
                                <p><strong>Vytvořen:</strong> {new Date(userDetail.user.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                        <h4>Aktivita</h4>
                        <div style={{maxHeight:'200px', overflowY:'auto'}}>
                            {userDetail.history && userDetail.history.map(h => (
                                <div key={h.id} style={{fontSize:'0.85rem', marginBottom:'5px', borderBottom:'1px solid #333', padding:'5px'}}>
                                    <span style={{color:'#666'}}>{new Date(h.timestamp).toLocaleString()}</span> - <strong>{h.action}</strong>
                                    {h.details && <div style={{color:'#888', fontStyle:'italic'}}>{h.details}</div>}
                                </div>
                            ))}
                        </div>
                        <button onClick={() => setUserDetail(null)} className="btn-cancel" style={{marginTop:'10px'}}>Zavřít</button>
                    </div>
                </div>
            )}

            {chatPreview && (
                <div className="modal-overlay" onClick={() => setChatPreview(null)}>
                    <div className="modal-content large" onClick={e => e.stopPropagation()}>
                        <h3>Náhled: {chatPreview.name || 'Chat'}</h3>
                        <div style={{flex:1, overflowY:'auto', background:'#0d0d0d', padding:'10px', borderRadius:'5px', marginBottom:'10px', height:'400px'}}>
                            {chatPreview.messages.length === 0 ? <p>Žádné zprávy</p> : chatPreview.messages.map((m, i) => (
                                <div key={i} style={{marginBottom:'10px', borderBottom:'1px solid #333', paddingBottom:'5px'}}>
                                    <strong style={{color:'#bb86fc'}}>{normalizeDisplayName(m.username)}</strong> <span style={{fontSize:'0.7rem', color:'#666'}}>{new Date(m.created_at).toLocaleString()}</span>
                                    <div style={{color:'#ddd', marginTop:'2px'}}>{m.content}</div>
                                </div>
                            ))}
                        </div>
                        <button onClick={() => setChatPreview(null)} className="btn-cancel">Zavřít náhled</button>
                    </div>
                </div>
            )}

            {showCreateAdmin && (
                <div className="modal-overlay">
                    <div className="modal-content">
                        <h3>Vytvořit nového Administrátora</h3>
                        <form onSubmit={handleCreateAdmin}>
                            <input type="text" placeholder="Jméno" required value={newAdminForm.username} onChange={e=>setNewAdminForm({...newAdminForm, username:e.target.value})} />
                            <input type="email" placeholder="Email" required value={newAdminForm.email} onChange={e=>setNewAdminForm({...newAdminForm, email:e.target.value})} />
                            <input type="password" placeholder="Heslo" required value={newAdminForm.password} onChange={e=>setNewAdminForm({...newAdminForm, password:e.target.value})} />
                            <div className="modal-actions">
                                <button type="button" onClick={() => setShowCreateAdmin(false)} className="btn-cancel">Zrušit</button>
                                <button type="submit" className="btn-create">Vytvořit</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default AdminPanel;