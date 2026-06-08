// src/App.js
import React, { useState, useEffect } from 'react';
import axios from 'axios';

function App() {
  const [menu, setMenu] = useState([]);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [token, setToken] = useState(null);

  useEffect(() => {
    axios.get('http://localhost:3000/menu')
      .then(res => setMenu(res.data))
      .catch(err => console.error(err));
  }, []);

  const login = () => {
    axios.post('http://localhost:3000/login', { email, password })
      .then(res => setToken(res.data.token))
      .catch(err => alert("Login failed"));
  };

  return (
    <div>
      <h1>Cafe Menu</h1>
      <ul>
        {menu.map(item => (
          <li key={item.menu_id}>{item.menu_name} - ${item.price}</li>
        ))}
      </ul>

      {!token ? (
        <div>
          <input type="text" placeholder="Email" value={email} onChange={e => setEmail(e.target.value)} />
          <input type="password" placeholder="Password" value={password} onChange={e => setPassword(e.target.value)} />
          <button onClick={login}>Login</button>
        </div>
      ) : (
        <p>Logged in with token: {token}</p>
      )}
    </div>
  );
}

export default App;