import { useContext, useState } from 'react';
import { AuthContext } from './context/AuthContext';
import Register from './components/Register';
import Login from './components/Login';
import UserList from './components/UserList';
import ProfileSetup from './components/ProfileSetup';
import AdminPanel from './components/AdminPanel';
import FriendManager from './components/FriendManager';
import ChatWindow from './components/ChatWindow';
import UserProfileModal from './components/UserProfileModal'; // <--- NOVÝ IMPORT
import './App.css';

// --- IKONY (SVG) ---
const SettingsIcon = () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1.82 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
);

const AddFriendIcon = () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
);

function App() {
    const { user, logout, api } = useContext(AuthContext);

    // --- STAVY APLIKACE ---
    const [showSettings, setShowSettings] = useState(false);
    const [showAdmin, setShowAdmin] = useState(false);
    const [showFriends, setShowFriends] = useState(false);
    const [viewingProfile, setViewingProfile] = useState(null); // <--- KDO SI PROHLÍŽÍME
    const [isLogin, setIsLogin] = useState(true);

    // Stavy pro Chat
    const [selectedChatUser, setSelectedChatUser] = useState(null);
    const [activeRoomId, setActiveRoomId] = useState(null);

    // --- PŘEPÍNAČE ---
    const toggleAdmin = () => { setShowAdmin(!showAdmin); setShowSettings(false); setShowFriends(false); };
    const toggleSettings = () => { setShowSettings(!showSettings); setShowAdmin(false); setShowFriends(false); };
    const toggleFriends = () => { setShowFriends(!showFriends); setShowSettings(false); setShowAdmin(false); };

    const handleUserSelect = async (friend) => {
        setSelectedChatUser(friend);
        setActiveRoomId(null);
        setShowSettings(false);
        setShowAdmin(false);

        try {
            const res = await api.post('/chat/open', { target_id: friend.id });
            if (res.data.room_id) {
                setActiveRoomId(res.data.room_id);
            }
        } catch (err) {
            console.error("Chyba chatu", err);
        }
    };

    return (
        <div className="app-layout">
            {!user ? (
                <div className="auth-wrapper">
                    <div className="auth-brand">
                        <h1 className="logo-text">Whisp</h1>
                        <p>Vítejte v bezpečné zóně</p>
                    </div>
                    <div className="auth-card">
                        {isLogin ? <Login /> : <Register />}
                        <div className="auth-toggle">
                            {isLogin ? (
                                <p>Nemáte ještě účet? <span onClick={() => setIsLogin(false)}>Zaregistrujte se</span></p>
                            ) : (
                                <p>Už máte účet? <span onClick={() => setIsLogin(true)}>Přihlaste se</span></p>
                            )}
                        </div>
                    </div>
                </div>
            ) : (
                <>
                    <header>
                        <div className="header-left">
                            <h1 className="app-logo">Whisp</h1>
                            {/* Kliknutí na vlastní jméno otevře můj profil (náhled) */}
                            <span
                                className="user-tag clickable"
                                onClick={() => setViewingProfile(user)}
                                title="Zobrazit můj profil"
                            >
                                @{user.username}
                            </span>
                        </div>

                        <div className="header-right">
                            {user.role === 'admin' && (
                                <button
                                    onClick={toggleAdmin}
                                    className={`admin-toggle-btn ${showAdmin ? 'active' : ''}`}
                                >
                                    {showAdmin ? "Zavřít" : "🛡️ Admin"}
                                </button>
                            )}

                            <button onClick={toggleFriends} className={`icon-btn ${showFriends ? 'active' : ''}`} title="Přátelé">
                                <AddFriendIcon />
                            </button>

                            <button onClick={toggleSettings} className={`icon-btn settings-btn ${showSettings ? 'active' : ''}`} title="Nastavení">
                                <SettingsIcon />
                            </button>

                            <button onClick={logout} className="logout-btn">Odhlásit</button>
                        </div>
                    </header>

                    <main className="main-content">
                        <aside className="sidebar">
                            <UserList onSelectUser={handleUserSelect} />
                        </aside>

                        <section className="chat-window">
                            {showAdmin ? (
                                <AdminPanel />
                            ) : showSettings ? (
                                <div className="settings-view">
                                    <button onClick={() => setShowSettings(false)} className="close-btn-text">
                                        &larr; Zpět do chatu
                                    </button>
                                    <ProfileSetup />
                                </div>
                            ) : (
                                selectedChatUser && activeRoomId ? (
                                    <ChatWindow
                                        selectedUser={selectedChatUser}
                                        roomId={activeRoomId}
                                        onProfileClick={(u) => setViewingProfile(u)} // <--- PŘEDÁVÁME FUNKCI
                                    />
                                ) : (
                                    <div className="welcome-hero">
                                        <h2>Vítej zpět, {user.username}!</h2>
                                        <p>Vyber si přítele ze seznamu nebo si někoho přidej ikonou nahoře.</p>
                                    </div>
                                )
                            )}
                        </section>
                    </main>

                    {/* MODÁLNÍ OKNA */}
                    {showFriends && (
                        <FriendManager
                            onClose={() => setShowFriends(false)}
                            onViewProfile={(u) => setViewingProfile(u)} // <--- PŘEDÁVÁME I SEM
                        />
                    )}

                    {viewingProfile && (
                        <UserProfileModal
                            user={viewingProfile}
                            onClose={() => setViewingProfile(null)}
                        />
                    )}
                </>
            )}
        </div>
    );
}

export default App;