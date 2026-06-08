const express = require('express');
const app = express();
const mysql = require('mysql2/promise');
const bcrypt = require('bcrypt');
const jwt = require('jsonwebtoken');

app.use(express.json());
const SECRET = 'your_jwt_secret_key';

// MySQL connection pool
const pool = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: 'yourpassword',
  database: 'cafe_digital_system',
});

// Register customer (including guest)
app.post('/register', async (req, res) => {
  const { name, email, password, phone, is_guest } = req.body;
  try {
    let hashedPassword = null;
    if (!is_guest && password) {
      hashedPassword = await bcrypt.hash(password, 10);
    }
    const [result] = await pool.query(
      'INSERT INTO CUSTOMER (name, email, password, phone, is_guest) VALUES (?, ?, ?, ?, ?)',
      [name, email || null, hashedPassword, phone || null, is_guest]
    );
    res.json({ customer_id: result.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Login customer
app.post('/login', async (req, res) => {
  const { email, password } = req.body;
  try {
    const [rows] = await pool.query('SELECT * FROM CUSTOMER WHERE email = ?', [email]);
    if (rows.length === 0) return res.status(401).json({ error: 'Invalid credentials' });
    const user = rows[0];
    const match = await bcrypt.compare(password, user.password);
    if (!match) return res.status(401).json({ error: 'Invalid credentials' });
    const token = jwt.sign({ customer_id: user.customer_id }, SECRET, { expiresIn: '1h' });
    res.json({ token });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Get available menu
app.get('/menu', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM MENU WHERE availability = TRUE');
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.listen(3000, () => {
  console.log('Server started on port 3000');
});