import React, { createContext, useState, useEffect } from 'react';
import axios from 'axios';

export const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    // Konfigurace Axiosu
    const api = axios.create({
        baseURL: 'http://localhost:8000/api'
    });

    // Přidání JWT tokenu do každého požadavku
    api.interceptors.request.use(config => {
        const token = localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    });

    // Kontrola přihlášení při startu (refresh stránky)
    useEffect(() => {
        const checkUser = async () => {
            const token = localStorage.getItem('token');
            if (token) {
                try {
                    const res = await api.get('/user/me');
                    setUser(res.data.user_data);
                } catch (err) {
                    logout();
                }
            }
            setLoading(false);
        };
        checkUser();
    }, []);

    const login = async (email, password) => {
        const res = await api.post('/login', { email, password });
        localStorage.setItem('token', res.data.token);
        setUser(res.data.user);
        return res.data;
    };

    const register = async (username, email, password) => {
        const res = await api.post('/register', { username, email, password });


        if (res.data.token) {
            localStorage.setItem('token', res.data.token);
            setUser(res.data.user);
        }
        return res.data;
    };

    const logout = async () => {
        try {

            await api.post('/logout');
        } catch (e) {
            console.error("Chyba při odhlašování na serveru", e);
        } finally {

            localStorage.removeItem('token');
            setUser(null);
        }
    };

    return (
        <AuthContext.Provider value={{ user, login, register, logout, api, loading }}>
            {!loading && children}
        </AuthContext.Provider>
    );
};