import React, { createContext, useState, useEffect, useRef } from 'react';
import axios from 'axios';

export const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const alertShown = useRef(false);

    const api = axios.create({
        baseURL: `http://${window.location.hostname}:8000/api`
    });

    // 1. REQUEST INTERCEPTOR (Přidává token do hlavičky)
    api.interceptors.request.use(config => {
        const token = localStorage.getItem('token');
        if (token) config.headers.Authorization = `Bearer ${token}`;
        return config;
    });

    // 2. RESPONSE INTERCEPTOR (Řeší 401 - Smazaný účet / Expirace)
    api.interceptors.response.use(
        (response) => response,
        (error) => {
            if (error.response && error.response.status === 401) {
                const token = localStorage.getItem('token');

                if (token && !alertShown.current) {
                    alertShown.current = true;

                    localStorage.removeItem('token');
                    setUser(null);


                    alert("Váš účet byl smazán nebo vypršela platnost přihlášení.");

                    window.location.href = '/';
                }
            }
            return Promise.reject(error);
        }
    );

    const normalizeUser = (u) => {
        if (!u) return null;
        const role = u.role ?? u.role_name ?? 'user';
        return { ...u, role };
    };

    useEffect(() => {
        const checkUser = async () => {
            const token = localStorage.getItem('token');
            if (token) {
                try {
                    const res = await api.get('/user/me');
                    setUser(normalizeUser(res.data.user));
                } catch (err) {
                    console.error("Auth check failed:", err);
                    // Interceptor výše to už odchytí, ale pro jistotu při startu:
                    if (err.response && err.response.status === 401) {
                        localStorage.removeItem('token');
                        setUser(null);
                    }
                }
            }
            setLoading(false);
        };
        checkUser();
    }, []);

    const login = async (email, password) => {
        const res = await api.post('/login', { email, password });
        localStorage.setItem('token', res.data.token);
        setUser(normalizeUser(res.data.user));
        return res.data;
    };

    const register = async (username, email, password) => {
        const res = await api.post('/register', { username, email, password });
        if (res.data.token) {
            localStorage.setItem('token', res.data.token);
            setUser(normalizeUser(res.data.user));
        }
        return res.data;
    };

    const logout = async () => {
        try { await api.post('/logout'); } catch (e) { console.warn("Logout error", e); }
        finally {
            localStorage.removeItem('token');
            setUser(null);
            window.location.href = '/'; 
        }
    };

    return (
        <AuthContext.Provider value={{ user, login, register, logout, loading, api }}>
            {!loading && children}
        </AuthContext.Provider>
    );
};