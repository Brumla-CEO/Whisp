import { useContext, useState } from 'react';
import { AuthContext } from './context/AuthContext';
import Register from './components/Register';
import Login from './components/Login';
import UserList from './components/UserList';
import ProfileSetup from './components/ProfileSetup';
import AdminPanel from './components/AdminPanel';
import './App.css';

function App() {
    const { user, logout } = useContext(AuthContext);
    const [showSettings, setShowSettings] = useState(false); // Stav pro nastavení profilu
    const [showAdmin, setShowAdmin] = useState(false);     // Stav pro admin rozhraní
    const [isLogin, setIsLogin] = useState(true);           // Přepínač Login/Register


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
                /* --- 1. AUTENTIKACE (Nepřihlášený uživatel) --- */
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
                        <h1 style={{ color: 'var(--accent)', margin: 0 }}>Whisp</h1>

                        <div className="user-nav" style={{ display: 'flex', alignItems: 'center', gap: '15px' }}>
                            {/* Admin tlačítko - nyní decentnější */}
                            {user.role === 'admin' && (
                                <button
                                    onClick={toggleAdmin}
                                    className={`admin-toggle-btn ${showAdmin ? 'active' : ''}`}
                                >
                                    {showAdmin ? "Zavřít Admin" : "🛡️ Admin Nástroje"}
                                </button>
                            )}

                            <span
                                onClick={toggleSettings}
                                style={{cursor: 'pointer', textDecoration: 'underline', fontSize: '0.9rem'}}
                            >
            {user.username} (Nastavení)
        </span>
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
                                    <button onClick={() => setShowSettings(false)} className="close-btn">
                                        ← Zpět do chatu
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