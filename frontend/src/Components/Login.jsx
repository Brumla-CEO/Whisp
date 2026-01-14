import React, { useState, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';

const Login = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const { login } = useContext(AuthContext);

    const handleLogin = async (e) => {
        e.preventDefault();
        try {
            await login(email, password);
        } catch (err) {
            setPassword('');
            alert('Špatný email nebo heslo');
        }
    };

    return (
        <div className="auth-card">
            <h2>Přihlášení</h2>
            <form onSubmit={handleLogin}>
                <input
                    type="email"
                    placeholder="Email"
                    value={email} // Přidáno pro kontrolu stavu
                    onChange={e => setEmail(e.target.value)}
                    required
                />
                <input
                    type="password"
                    placeholder="Heslo"
                    value={password} // Přidáno pro kontrolu stavu
                    onChange={e => setPassword(e.target.value)}
                    required
                />
                <button type="submit">Přihlásit se</button>
            </form>
        </div>
    );
};

export default Login;