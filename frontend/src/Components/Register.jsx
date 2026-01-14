import React, { useState, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';

const Register = () => {
    const [data, setData] = useState({ username: '', email: '', password: '' });
    const { register } = useContext(AuthContext);

    const handleForm = async (e) => {
        e.preventDefault();
        try {
            await register(data.username, data.email, data.password);
        } catch (err) {
            alert(err.response?.data?.message || 'Chyba registrace');
        }
    };

    return (
        <div className="auth-card">
            <h2>Registrace do Whispu</h2>
            <form onSubmit={handleForm}>
                <input type="text" placeholder="Username" onChange={e => setData({...data, username: e.target.value})} required />
                <input type="email" placeholder="Email" onChange={e => setData({...data, email: e.target.value})} required />
                <input type="password" placeholder="Heslo" onChange={e => setData({...data, password: e.target.value})} required />
                <button type="submit">Vytvořit účet</button>
            </form>
        </div>
    );
};

export default Register;