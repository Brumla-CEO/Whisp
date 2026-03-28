import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../Context/AuthContext';

const CreateGroupModal = ({ onClose, onGroupCreated, socket }) => {
    const { api } = useContext(AuthContext);
    const [groupName, setGroupName] = useState('');
    const [friends, setFriends] = useState([]);
    const [selectedFriends, setSelectedFriends] = useState([]);
    const [error, setError] = useState('');

    useEffect(() => {
        const loadFriends = async () => {
            try {
                const res = await api.get('/friends');
                setFriends(res.data);
            } catch (err) {
                console.error("Chyba načítání přátel", err);
            }
        };
        loadFriends();
    }, [api]);

    const toggleFriend = (friendId) => {
        if (selectedFriends.includes(friendId)) {
            setSelectedFriends(prev => prev.filter(id => id !== friendId));
        } else {
            setSelectedFriends(prev => [...prev, friendId]);
        }
    };

    const handleCreate = async () => {
        setError('');

        if (!groupName.trim()) return setError("Zadejte název skupiny.");

        if (selectedFriends.length < 2) {
            return setError("Skupina musí mít alespoň 3 členy (vy + minimálně 2 přátelé).");
        }

        try {
            const res = await api.post('/groups/create', {
                name: groupName,
                members: selectedFriends
            });

            if (socket && socket.readyState === WebSocket.OPEN && res.data.room_id) {
                socket.send(JSON.stringify({
                    type: 'group_change',
                    roomId: res.data.room_id
                }));
            }
            onGroupCreated();
            onClose();
        } catch (err) {
            setError("Nepodařilo se vytvořit skupinu.");
            console.error(err);
        }
    };

    return (
        <div className="modal-overlay">
            <div className="modal-content">
                <h3>Vytvořit novou skupinu</h3>

                {error && <p className="error-msg" style={{color: '#cf6679', marginBottom: '10px'}}>{error}</p>}

                <input
                    type="text"
                    placeholder="Název skupiny (např. Projekt X)"
                    value={groupName}
                    onChange={e => setGroupName(e.target.value)}
                    className="modal-input"
                />

                <div className="friends-selection-list">
                    <p>Vyberte členy ({selectedFriends.length}):</p>
                    {friends.length === 0 && <p style={{color: '#888', fontStyle: 'italic'}}>Nemáte žádné přátele k přidání.</p>}

                    {friends.map(friend => (
                        <div
                            key={friend.id}
                            className={`friend-select-item ${selectedFriends.includes(friend.id) ? 'selected' : ''}`}
                            onClick={() => toggleFriend(friend.id)}
                        >
                            <img src={friend.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${friend.username}`} alt="Avatar" />
                            <span>{friend.username}</span>
                            {selectedFriends.includes(friend.id) && <span className="check-mark">✔</span>}
                        </div>
                    ))}
                </div>

                <div className="modal-actions">
                    <button onClick={onClose} className="btn-cancel">Zrušit</button>
                    <button onClick={handleCreate} className="btn-create">Vytvořit</button>
                </div>
            </div>
        </div>
    );
};

export default CreateGroupModal;