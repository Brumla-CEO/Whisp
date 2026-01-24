import React, { useEffect, useState, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';

const AdminPanel = () => {
    const [stats, setStats] = useState({ logs: [], users: [], total_users: 0 });
    const { api } = useContext(AuthContext);

    useEffect(() => {
        const fetchAdminData = async () => {
            try {
                const res = await api.get('/admin/stats');
                setStats(res.data);
            } catch (err) {
                console.error("Nepodařilo se načíst admin data");
            }
        };
        fetchAdminData();
    }, []);

    return (
        <div className="admin-panel">
            <header className="admin-header">
                <h2>🛡️ Admin Control Panel</h2>
                <div className="stat-badge">Celkem uživatelů: {stats.total_users}</div>
            </header>

            <section className="admin-section">
                <h3>Poslední aktivita (Logs)</h3>
                <div className="logs-table-wrapper">
                    <table>
                        <thead>
                        <tr>
                            <th>Uživatel</th>
                            <th>Akce</th>
                            <th>IP Adresa</th>
                            <th>Čas</th>
                        </tr>
                        </thead>
                        <tbody>
                        {stats.logs.map(log => (
                            <tr key={log.id}>
                                <td>{log.username || 'Smazaný uživatel'}</td>
                                <td><span className={`badge ${log.action}`}>{log.action}</span></td>
                                <td>{log.ip_address}</td>
                                <td>{new Date(log.timestamp).toLocaleString()}</td>
                            </tr>
                        ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
};

export default AdminPanel;