import React, { useState, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';

const ProfileSetup = () => {
    const { user, api } = useContext(AuthContext);
    // Předvyplníme stávající data z kontextu
    const [bio, setBio] = useState(user.bio || '');
    const [avatar, setAvatar] = useState(user.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${user.username}`);

    const handleSave = async (e) => {
        e.preventDefault();
        try {
            await api.put(`/users/${user.sub}`, {
                username: user.username,
                email: user.email,
                bio: bio,
                avatar_url: avatar
            });
            alert('Profil aktualizován!');
            window.location.reload(); // Obnovíme data
        } catch (err) {
            alert('Chyba při ukládání.');
        }
    };

    return (
        <div className="edit-profile-form">
            <h3>Upravit tvůj profil</h3>
            <form onSubmit={handleSave}>
                <div className="avatar-preview">
                    <img src={avatar} alt="Avatar" />
                    <button type="button" onClick={() => setAvatar(`https://api.dicebear.com/7.x/avataaars/svg?seed=${Math.random()}`)}>
                        Náhodný avatar
                    </button>
                </div>
                <label>Tvé BIO:</label>
                <textarea value={bio} onChange={e => setBio(e.target.value)} />
                <button type="submit">Uložit změny</button>
            </form>
        </div>
    );
};

export default ProfileSetup;