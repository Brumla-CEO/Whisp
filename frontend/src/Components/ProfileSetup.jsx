import React, { useState, useContext, useEffect } from 'react';
import { AuthContext } from '../Context/AuthContext';

const ProfileSetup = ({ socket }) => {
    const { user, api, logout } = useContext(AuthContext);

    // Stavy formuláře
    const [username, setUsername] = useState(user.username || '');
    const [bio, setBio] = useState(user.bio || '');

    // Logika Avataru
    const [avatarType, setAvatarType] = useState('random');
    const [customAvatarUrl, setCustomAvatarUrl] = useState('');
    const [currentSeed, setCurrentSeed] = useState(user.username);

    // Mazání účtu
    const [deleteMode, setDeleteMode] = useState(false);
    const [deleteConfirmation, setDeleteConfirmation] = useState('');

    // Synchronizace formuláře při načtení
    useEffect(() => {
        setUsername(user.username || '');
        setBio(user.bio || '');

        if (user.avatar_url) {
            if (user.avatar_url.includes('dicebear')) {
                setAvatarType('random');
                try {
                    const urlObj = new URL(user.avatar_url);
                    const seedParam = urlObj.searchParams.get("seed");
                    if (seedParam) setCurrentSeed(seedParam);
                } catch (e) {}
            } else {
                setAvatarType('custom');
                setCustomAvatarUrl(user.avatar_url);
            }
        }
    }, [user]);

    const generateRandomAvatar = () => setCurrentSeed(Math.random().toString(36).substring(7));

    const getPreviewUrl = () => {
        if (avatarType === 'random') return `https://api.dicebear.com/7.x/avataaars/svg?seed=${currentSeed}`;
        if (!customAvatarUrl) return `https://api.dicebear.com/7.x/initials/svg?seed=URL`;
        return customAvatarUrl;
    };

    const handleSave = async (e) => {
        e.preventDefault();
        try {
            const finalAvatarUrl = avatarType === 'random'
                ? `https://api.dicebear.com/7.x/avataaars/svg?seed=${currentSeed}`
                : customAvatarUrl;

            // 1. Uložit do DB
            await api.put(`/users/${user.id}`, {
                username: username,
                email: user.email,
                bio: bio,
                avatar_url: finalAvatarUrl
            });

            // 2. Říct ostatním "Změnil jsem se" (před refreshem)
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({ type: 'profile_change' }));
            }

            alert('Profil úspěšně aktualizován!');

            // 3. HARD REFRESH STRÁNKY (F5)
            // Toto zajistí, že se načtou nová data z DB a vše se aktualizuje u vás
            window.location.reload();

        } catch (err) {
            console.error(err);
            alert(err.response?.data?.message || 'Chyba při ukládání.');
        }
    };

    const handleDeleteProfile = async () => {
        if (deleteConfirmation !== user.username) {
            alert('Jméno nesouhlasí, profil nebyl smazán.');
            return;
        }
        try {
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({
                    type: 'contact_deleted',
                    userId: user.id
                }));
            }

            await api.delete(`/users/${user.id}`);

            alert('Váš profil byl smazán. Nashledanou.');
            logout();
        } catch (err) {
            alert('Chyba při mazání profilu: ' + (err.response?.data?.message || err.message));
        }
    };

    return (
        <div className="edit-profile-container">
            <h3>Nastavení profilu</h3>
            <p className="subtitle">Spravujte svou identitu na Whispu</p>

            <form onSubmit={handleSave} className="profile-form">
                <div className="form-section avatar-section">
                    <div className="avatar-preview-large">
                        <img
                            src={getPreviewUrl()}
                            alt="Avatar Preview"
                            onError={(e) => { e.target.onerror = null; e.target.src = `https://api.dicebear.com/7.x/initials/svg?seed=Error`; }}
                        />
                    </div>

                    <div className="avatar-controls">
                        <label>Profilový obrázek</label>
                        <div className="radio-group">
                            <label className={`radio-btn ${avatarType === 'random' ? 'active' : ''}`}>
                                <input type="radio" name="avatarType" value="random" checked={avatarType === 'random'} onChange={() => setAvatarType('random')} />
                                Generovaný
                            </label>
                            <label className={`radio-btn ${avatarType === 'custom' ? 'active' : ''}`}>
                                <input type="radio" name="avatarType" value="custom" checked={avatarType === 'custom'} onChange={() => setAvatarType('custom')} />
                                Vlastní URL
                            </label>
                        </div>

                        {avatarType === 'random' ? (
                            <button type="button" className="secondary-btn" onClick={generateRandomAvatar}>🎲 Přegevenerovat</button>
                        ) : (
                            <input type="text" placeholder="https://imgur.com/..." value={customAvatarUrl} onChange={(e) => setCustomAvatarUrl(e.target.value)} />
                        )}
                    </div>
                </div>

                <hr className="divider"/>

                <div className="form-section">
                    <label>Uživatelské jméno</label>
                    <div className="input-group">
                        <span className="input-prefix">@</span>
                        <input type="text" value={username} onChange={e => setUsername(e.target.value)} required />
                    </div>
                </div>

                <div className="form-section">
                    <label>O mně (Bio)</label>
                    <textarea value={bio} onChange={e => setBio(e.target.value)} maxLength={200} placeholder="Napiš něco o sobě..." />
                    <div className="char-count">{bio.length} / 200</div>
                </div>

                <button type="submit" className="save-btn">Uložit změny</button>
            </form>

            <hr className="divider"/>

            <div className="danger-zone">
                <h4>Odstranění účtu</h4>
                <p>Tato akce je nevratná. Všechny vaše zprávy a data budou vymazány.</p>

                {!deleteMode ? (
                    <button type="button" className="delete-btn-init" onClick={() => setDeleteMode(true)}>Chci smazat svůj profil</button>
                ) : (
                    <div className="delete-confirmation">
                        <p>Pro potvrzení napište své uživatelské jméno: <strong>{user.username}</strong></p>
                        <input type="text" placeholder={user.username} value={deleteConfirmation} onChange={e => setDeleteConfirmation(e.target.value)} />
                        <div className="delete-actions">
                            <button type="button" className="cancel-btn" onClick={() => setDeleteMode(false)}>Zrušit</button>
                            <button type="button" className="delete-btn-final" onClick={handleDeleteProfile}>Navždy odstranit</button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default ProfileSetup;