import React, { useState, useContext } from 'react';
import { AuthContext } from '../Context/AuthContext';

const Register = () => {
    const [data, setData] = useState({ username: '', email: '', password: '' });
    const [error, setError] = useState('');
    const { register } = useContext(AuthContext);

    const handleForm = async (e) => {
        e.preventDefault();

        const username = data.username.trim();
        const email = data.email.trim();
        const password = data.password;

        if (!username || !email || !password) {
            setError('Vyplň všechna pole.');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setError('Zadej platný email.');
            return;
        }

        if (password.length < 6) {
            setError('Heslo musí mít alespoň 6 znaků.');
            return;
        }

        try {
            setError('');
            await register(username, email, password);
        } catch (err) {
            setError(err.response?.data?.message || 'Chyba registrace');
        }
    };

    return (
        <div className="auth-card">
            <h2>Registrace do Whispu</h2>
            <form onSubmit={handleForm}>
                <input
                    type="text"
                    placeholder="Username"
                    value={data.username}
                    onChange={e => setData({ ...data, username: e.target.value })}
                    required
                />
                <input
                    type="email"
                    placeholder="Email"
                    value={data.email}
                    onChange={e => setData({ ...data, email: e.target.value })}
                    required
                />
                <input
                    type="password"
                    placeholder="Heslo"
                    value={data.password}
                    onChange={e => setData({ ...data, password: e.target.value })}
                    minLength={6}
                    required
                />
                {error && <div className="auth-error">{error}</div>}
                <button type="submit">Vytvořit účet</button>
            </form>
        </div>
    );
};

export default Register;
