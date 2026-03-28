import React, { useContext } from 'react';
import { AuthContext } from '../Context/AuthContext';
import { canShowUnfriendButton } from '../utils/friendChatState';

const notify = (message, type = 'info', timeout = 4200) => {
    window.dispatchEvent(new CustomEvent('app-notify', { detail: { message, type, timeout } }));
};

const UserProfileModal = ({ user, onClose, socket, onFriendRemoved }) => {
    const { api, user: currentUser } = useContext(AuthContext);

    if (!user) return null;

    const isOwnProfile = currentUser && String(currentUser.id) === String(user.id);
    const displayStatus = isOwnProfile ? 'online' : (user.status ?? 'offline');

    const canUnfriend = canShowUnfriendButton(currentUser, user);

    const handleUnfriend = async () => {
        if (!confirm(`Opravdu chcete odebrat ${user.username} z přátel? Otevřený DM chat se ihned zavře.`)) return;
        try {
            await api.post('/friends/remove', { friend_id: user.id });

            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({
                    type: 'friend_action',
                    targetId: user.id,
                    action: 'unfriended'
                }));
            }

            if (onFriendRemoved) {
                onFriendRemoved(user.id, 'silent');
            }

            window.dispatchEvent(new CustomEvent('friend-status-change', { detail: { refresh: true } }));
            window.dispatchEvent(new CustomEvent('friend-removed', { detail: { userId: user.id } }));
            notify(`Uživatel ${user.username} byl odebrán z přátel.`, 'success');
            onClose();
        } catch (e) {
            notify('Chyba při odebírání přítele.', 'error');
        }
    };

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-content profile-view-card" onClick={e => e.stopPropagation()}>
                <div className="profile-view-header"><button onClick={onClose} className="close-btn-icon">✕</button></div>
                <div className="profile-view-body">
                    <div className="profile-view-avatar">
                        <img src={user.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${user.username}`} alt={user.username} />
                        <span className={`status-indicator-large ${displayStatus}`}></span>
                    </div>
                    <h2>{user.username}</h2>
                    <span className="profile-role">{user.role === 'admin' ? '🛡️ Admin' : 'Uživatel'}</span>

                    <div className="profile-bio-section"><label>O mně</label><p>{user.bio || 'Nic tu není.'}</p></div>

                    {canUnfriend && (
                        <button onClick={handleUnfriend} className="btn-danger-small" style={{ marginTop: '20px', padding: '10px', width: '100%', border: '1px solid #cf6679', color: '#cf6679' }}>
                            Odebrat z přátel
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
};
export default UserProfileModal;
