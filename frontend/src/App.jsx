import { useEffect, useState } from 'react';
import './App.css';

function App() {
    const [message, setMessage] = useState('Loading...');

    useEffect(() => {
        async function fetchHello() {
            try {
                const response = await fetch('http://localhost:8000/api/test.php'); // local mode
                // OR for Docker deployment:
                // const response = await fetch('http://backend:8000/api/test.php');
                const data = await response.json();
                setMessage(data.message);
            } catch (err) {
                setMessage(`Error: ${err}`);
            }
        }
        fetchHello();
    }, []);


    return (
        <div className="App">
            <h1>{message}</h1>
        </div>
    );
}

export default App;
