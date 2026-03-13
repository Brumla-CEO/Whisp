import React, { useState, useContext } from 'react';
import { AuthContext } from '../Context/AuthContext';

const Login = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const { login } = useContext(AuthContext);

    const handleLogin = async (e) => {
        e.preventDefault();

        try {
            setError('');
            await login(email.trim(), password);
        } catch (err) {
            setPassword('');
            setError(err.response?.data?.message || 'Neplatný email nebo heslo');
        }
    };

    return (
        <div className="auth-card">
            <h2>Přihlášení</h2>
            <form onSubmit={handleLogin}>
                <input
                    type="email"
                    placeholder="Email"
                    value={email}
                    onChange={e => setEmail(e.target.value)}
                    required
                />
                <input
                    type="password"
                    placeholder="Heslo"
                    value={password}
                    onChange={e => setPassword(e.target.value)}
                    required
                />
                {error && <div className="auth-error">{error}</div>}
                <button type="submit">Přihlásit se</button>
            </form>
        </div>
    );
};

export default Login;
