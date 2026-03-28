import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../Context/AuthContext';

const normalizeDisplayName = (name) => {
    if (!name) return 'Neznámý uživatel';
    return String(name).startsWith('deleted_') ? 'Smazaný uživatel' : name;
};

const GroupDetailsModal = ({ group, onClose, onLeaveGroup, socket }) => { // <-- PŘIJETÍ SOCKETU
    const { api, user } = useContext(AuthContext);
    const [members, setMembers] = useState([]);
    const [friendsToAdd, setFriendsToAdd] = useState([]);
    const [isAdding, setIsAdding] = useState(false);

    const [isEditing, setIsEditing] = useState(false);

    const [currentName, setCurrentName] = useState(group.name);
    const [currentAvatar, setCurrentAvatar] = useState(group.avatar_url);

    const [newName, setNewName] = useState(group.name);
    const [newAvatar, setNewAvatar] = useState(group.avatar_url || '');

    const [imgError, setImgError] = useState(false);
    const [urlError, setUrlError] = useState(false);

    const myId = user.id || user.sub;
    const amIAdmin = members.find(m => String(m.id) === String(myId))?.role === 'admin';

    const fetchMembers = async () => {
        try {
            const res = await api.get(`/groups/members?room_id=${group.id}`);
            setMembers(res.data);
        } catch (err) { console.error(err); }
    };

    useEffect(() => { fetchMembers(); }, [group.id]);

    useEffect(() => {
        setImgError(false);
        if (newAvatar && newAvatar.trim().length > 0) {
            try { new URL(newAvatar); setUrlError(false); } catch (_) { setUrlError(true); }
        } else { setUrlError(false); }
    }, [newAvatar]);

    const notifyGroupChange = () => {
        if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({
                type: 'group_change',
                roomId: group.id
            }));
        }
    };

    const loadFriendsToAdd = async () => {
        setIsAdding(true);
        try {
            const res = await api.get('/friends');
            const memberIds = members.map(m => m.id);
            setFriendsToAdd(res.data.filter(f => !memberIds.includes(f.id)));
        } catch (err) { console.error(err); }
    };

    const handleAddMember = async (friendId) => {
        try {
            await api.post('/groups/add-member', { room_id: group.id, user_id: friendId });
            fetchMembers();
            setFriendsToAdd(prev => prev.filter(f => f.id !== friendId));
            notifyGroupChange(); // <--- OZNÁMIT
        } catch (err) { alert("Chyba při přidávání"); }
    };

    const handleKick = async (memberId, memberName) => {
        if (!window.confirm(`Opravdu vyhodit uživatele ${memberName}?`)) return;
        try {
            await api.post('/groups/kick', { room_id: group.id, user_id: memberId });
            fetchMembers();

            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({
                    type: 'group_kick',
                    roomId: group.id,
                    kickedUserId: memberId,
                    groupName: group.name
                }));
            } else {
                notifyGroupChange();
            }

        } catch (err) { alert("Chyba při vyhazování"); }
    };

    const handleLeave = async () => {
        if (!window.confirm(members.length === 1 ? "Jste poslední člen. Skupina bude smazána." : "Opravdu odejít?")) return;
        try {
            await api.post('/groups/leave', { room_id: group.id });
            onLeaveGroup();
            onClose();
        } catch (err) { alert("Chyba při opouštění"); }
    };

    const handleUpdateGroup = async () => {
        const finalAvatar = (urlError || imgError || !newAvatar.trim()) ? '' : newAvatar;

        try {
            await api.post('/groups/update', {
                room_id: group.id,
                name: newName,
                avatar_url: finalAvatar
            });

            setCurrentName(newName);
            setCurrentAvatar(finalAvatar);
            setIsEditing(false);

            window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
            window.dispatchEvent(new CustomEvent('group-updated', {
                detail: { roomId: group.id, name: newName, avatar_url: finalAvatar }
            }));

            notifyGroupChange();

        } catch (err) { console.error(err); alert("Chyba při úpravě skupiny"); }
    };

    const getDisplayAvatar = () => {
        const getDiceBear = (seed) => `https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(seed || 'Group')}`;
        if (isEditing) {
            if (urlError || imgError || !newAvatar || !newAvatar.trim()) return getDiceBear(newName);
            return newAvatar;
        }
        if (!currentAvatar || currentAvatar.trim() === '' || imgError) return getDiceBear(currentName);
        return currentAvatar;
    };

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-content" style={{ maxWidth: '450px' }} onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    {isEditing ? (
                        <input
                            value={newName}
                            onChange={e => setNewName(e.target.value)}
                            className="modal-input"
                            style={{margin: 0, fontWeight: 'bold', fontSize: '1.1rem'}}
                            placeholder="Název skupiny"
                        />
                    ) : (
                        <h3>{currentName}</h3>
                    )}
                    <button onClick={onClose} className="close-btn-icon">✕</button>
                </div>

                <div className="modal-body">
                    <div style={{ textAlign: 'center', marginBottom: '20px' }}>
                        <img
                            src={getDisplayAvatar()}
                            onError={() => setImgError(true)}
                            style={{
                                width: '100px', height: '100px',
                                borderRadius: '50%', objectFit: 'cover',
                                border: isEditing ? '2px dashed #bb86fc' : '4px solid #1e1e1e',
                                boxShadow: '0 4px 10px rgba(0,0,0,0.3)',
                                backgroundColor: '#1e1e1e'
                            }}
                            alt="Group Avatar"
                        />

                        {isEditing && (
                            <div style={{ marginTop: '10px' }}>
                                <input
                                    type="text"
                                    placeholder="URL obrázku (https://...)"
                                    value={newAvatar}
                                    onChange={e => setNewAvatar(e.target.value)}
                                    className="modal-input"
                                    style={{ fontSize: '0.8rem', padding: '8px', marginBottom: '5px', borderColor: (urlError || imgError) ? '#cf6679' : '#444' }}
                                />
                                {urlError ? <p style={{fontSize: '0.75rem', color: '#cf6679', margin: 0}}>⚠ Neplatný formát URL.</p>
                                    : imgError && newAvatar ? <p style={{fontSize: '0.75rem', color: '#cf6679', margin: 0}}>⚠ Obrázek nelze načíst.</p>
                                        : <p style={{fontSize: '0.7rem', color: '#666', margin: 0}}>Nechte prázdné pro výchozí iniciály.</p>}
                            </div>
                        )}

                        {!isEditing && (
                            <p style={{ color: '#aaa', fontSize: '0.9rem', marginTop: '10px' }}>
                                {members.length} členů
                                {amIAdmin && (
                                    <span
                                        onClick={() => {
                                            setIsEditing(true);
                                            setNewName(currentName);
                                            setNewAvatar(currentAvatar || '');
                                            setImgError(false); setUrlError(false);
                                        }}
                                        style={{ color: '#bb86fc', cursor: 'pointer', marginLeft: '10px', fontSize: '0.8rem', fontWeight: 'bold' }}
                                    >
                                        ✎ Upravit
                                    </span>
                                )}
                            </p>
                        )}

                        {isEditing && (
                            <div style={{ display: 'flex', gap: '10px', justifyContent: 'center', marginTop: '15px' }}>
                                <button onClick={() => {
                                    setIsEditing(false); setImgError(false); setUrlError(false);
                                    setNewName(currentName); setNewAvatar(currentAvatar);
                                }} className="btn-cancel" style={{padding: '6px 15px'}}>Zrušit</button>
                                <button onClick={handleUpdateGroup} className="btn-create" style={{padding: '6px 15px'}}>Uložit</button>
                            </div>
                        )}
                    </div>

                    <h4 style={{borderBottom: '1px solid #333', paddingBottom: '5px', marginBottom: '10px'}}>Členové</h4>
                    <div className="friends-selection-list" style={{ maxHeight: '200px', border: 'none', padding: 0 }}>
                        {members.map(member => (
                            <div key={member.id} className="user-card-row" style={{ justifyContent: 'space-between', background: 'transparent', borderBottom: '1px solid #2c2c2c', borderRadius: 0 }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                    <div style={{ position: 'relative' }}>
                                        <img src={member.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${normalizeDisplayName(member.username)}`} alt="Avatar" style={{width: '35px', height: '35px', border: 'none'}} />
                                        <span className={`status-indicator ${member.status === 'online' ? 'online' : 'offline'}`}
                                              style={{ width: '8px', height: '8px', right: '0', bottom: '0', border: '2px solid #1e1e1e' }}></span>
                                    </div>
                                    <div className="user-info-col">
                                        <span style={{ color: 'white', fontWeight: '500' }}>{normalizeDisplayName(member.username)}</span>
                                        <span style={{ fontSize: '0.7rem', color: '#888' }}>{member.role === 'admin' ? '👑 Správce' : (member.status === 'online' ? 'Online' : 'Offline')}</span>
                                    </div>
                                </div>
                                {amIAdmin && String(member.id) !== String(myId) && (
                                    <button onClick={() => handleKick(member.id, member.username)} className="btn-danger-small" style={{ fontSize: '0.65rem', padding: '3px 8px' }}>Vyhodit</button>
                                )}
                            </div>
                        ))}
                    </div>

                    {!isAdding ? (
                        <button onClick={loadFriendsToAdd} className="secondary-btn" style={{ marginTop: '15px', width: '100%', borderRadius: '8px' }}>+ Přidat další lidi</button>
                    ) : (
                        <div style={{ marginTop: '15px', borderTop: '1px solid #333', paddingTop: '10px', animation: 'fadeIn 0.2s' }}>
                            <h5 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#bb86fc' }}>Vyberte přátele k přidání:</h5>
                            {friendsToAdd.length === 0 && <p style={{ color: '#666', fontSize: '0.8rem', fontStyle: 'italic' }}>Žádní další přátelé k dispozici.</p>}
                            <div className="friends-selection-list" style={{ maxHeight: '150px', border: '1px solid #333', borderRadius: '8px', padding: '5px' }}>
                                {friendsToAdd.map(friend => (
                                    <div key={friend.id} className="user-card-row" style={{ justifyContent: 'space-between', padding: '8px' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                            <img src={friend.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${normalizeDisplayName(friend.username)}`} alt="Av" style={{width: '30px', height: '30px'}} />
                                            <span>{normalizeDisplayName(friend.username)}</span>
                                        </div>
                                        <button onClick={() => handleAddMember(friend.id)} className="add-btn" style={{padding: '4px 10px'}}>Přidat</button>
                                    </div>
                                ))}
                            </div>
                            <button onClick={() => setIsAdding(false)} style={{ background: 'none', border: 'none', color: '#aaa', fontSize: '0.8rem', marginTop: '5px', cursor: 'pointer', width: '100%' }}>▲ Skrýt výběr</button>
                        </div>
                    )}
                </div>

                <div className="modal-actions" style={{ justifyContent: 'center', padding: '20px', borderTop: '1px solid #333' }}>
                    <button onClick={handleLeave} className="delete-btn-init" style={{ width: '100%' }}>Opustit skupinu</button>
                </div>
            </div>
        </div>
    );
};

export default GroupDetailsModal;