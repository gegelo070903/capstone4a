import React from 'react';
import { Link } from 'react-router-dom';

const Navbar = ({ user }) => (
  <nav>
    <ul style={{ position: 'fixed', top: 0, left: 0 }}>
      <li><Link to="/">Dashboard</Link></li>
      <li><Link to="/supply-monitoring">Supply Monitoring</Link></li>
      <li><Link to="/development-monitoring">Development Monitoring</Link></li>
      {user && user.role === 'admin' && (
        <li><Link to="/full-reports">Full Reports (PDF)</Link></li>
      )}
    </ul>
  </nav>
);

export default Navbar;