import React from 'react';

const UserProfileModal = ({ user, onClose }) => {
    if (!user) return null;

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-content profile-view-card" onClick={e => e.stopPropagation()}>
                <div className="profile-view-header">
                    <button onClick={onClose} className="close-btn-icon">✕</button>
                </div>

                <div className="profile-view-body">
                    <div className="profile-view-avatar">
                        <img
                            src={user.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${user.username}`}
                            alt={user.username}
                        />
                        <span className={`status-indicator-large ${user.status || 'offline'}`}></span>
                    </div>

                    <h2>{user.username}</h2>
                    <span className="profile-role">{user.role === 'admin' ? '🛡️ Admin' : 'Uživatel'}</span>

                    <div className="profile-bio-section">
                        <label>O mně</label>
                        <p>{user.bio || "Tento uživatel o sobě zatím nic nenapsal."}</p>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default UserProfileModal;