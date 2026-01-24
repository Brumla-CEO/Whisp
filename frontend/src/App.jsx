import { useContext, useState } from 'react';
import { AuthContext } from './context/AuthContext';
import Register from './components/Register';
import Login from './components/Login';
import UserList from './components/UserList';
import ProfileSetup from './components/ProfileSetup';
import AdminPanel from './components/AdminPanel';
import './App.css';

// SVG Ikona ozubeného kolečka
const SettingsIcon = () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1.82 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
    </svg>
);

function App() {
    const { user, logout } = useContext(AuthContext);
    const [showSettings, setShowSettings] = useState(false);
    const [showAdmin, setShowAdmin] = useState(false);
    const [isLogin, setIsLogin] = useState(true);

    const toggleAdmin = () => {
        setShowAdmin(!showAdmin);
        setShowSettings(false);
    };

    const toggleSettings = () => {
        setShowSettings(!showSettings);
        setShowAdmin(false);
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
                        {/* LEVÁ STRANA: Logo + User Tag */}
                        <div className="header-left">
                            <h1 className="app-logo">Whisp</h1>
                            <span className="user-tag">@{user.username}</span>
                        </div>

                        {/* PRAVÁ STRANA: Ovládací prvky */}
                        <div className="header-right">
                            {user.role === 'admin' && (
                                <button
                                    onClick={toggleAdmin}
                                    className={`admin-toggle-btn ${showAdmin ? 'active' : ''}`}
                                >
                                    {showAdmin ? "Zavřít" : "🛡️ Admin"}
                                </button>
                            )}

                            <button
                                onClick={toggleSettings}
                                className={`icon-btn settings-btn ${showSettings ? 'active' : ''}`}
                                title="Nastavení profilu"
                            >
                                <SettingsIcon />
                            </button>

                            <button onClick={logout} className="logout-btn">Odhlásit</button>
                        </div>
                    </header>

                    <main className="main-content">
                        <aside className="sidebar">
                            <UserList />
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
                                <div className="welcome-hero">
                                    <h2>Vítej zpět, {user.username}!</h2>
                                    <p>Vyber si kontakt vlevo a začni psát.</p>
                                </div>
                            )}
                        </section>
                    </main>
                </>
            )}
        </div>
    );
}

export default App;