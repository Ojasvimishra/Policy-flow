import React from 'react';
import { NavLink } from 'react-router-dom';
import { Shield, LayoutDashboard, FileText, PlusCircle, SlidersHorizontal, X } from 'lucide-react';

interface SidebarProps {
  isOpen: boolean;
  onClose: () => void;
}

export const Sidebar: React.FC<SidebarProps> = ({ isOpen, onClose }) => {
  return (
    <>
      {isOpen && (
        <div
          className="sidebar-backdrop"
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(17, 24, 39, 0.4)',
            zIndex: 35,
          }}
          onClick={onClose}
          aria-hidden="true"
        />
      )}

      <aside className={`sidebar ${isOpen ? 'open' : ''}`} aria-label="Main Navigation">
        <div className="brand-area">
          <div className="brand-icon" aria-hidden="true">
            <Shield size={18} />
          </div>
          <div style={{ flex: 1 }}>
            <div className="brand-name">PolicyFlow</div>
            <span className="brand-badge">Operations UI</span>
          </div>
          {isOpen && (
            <button
              className="btn btn-ghost btn-sm"
              style={{ display: 'none' }}
              onClick={onClose}
              aria-label="Close sidebar"
            >
              <X size={18} />
            </button>
          )}
        </div>

        <nav className="nav-menu">
          <NavLink
            to="/"
            end
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
            onClick={onClose}
          >
            <LayoutDashboard size={18} />
            <span>Overview</span>
          </NavLink>

          <NavLink
            to="/policies"
            end
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
            onClick={onClose}
          >
            <FileText size={18} />
            <span>Policy Requests</span>
          </NavLink>

          <NavLink
            to="/policies/new"
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
            onClick={onClose}
          >
            <PlusCircle size={18} />
            <span>New Policy</span>
          </NavLink>

          <NavLink
            to="/operations"
            className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
            onClick={onClose}
          >
            <SlidersHorizontal size={18} />
            <span>Operations & Recon</span>
          </NavLink>
        </nav>

        <div className="sidebar-footer">
          <div>PolicyFlow Engine v1.0</div>
          <div style={{ color: 'var(--color-primary)', fontWeight: 500, marginTop: '0.2rem' }}>
            Phase 4: Operations Portal
          </div>
        </div>
      </aside>
    </>
  );
};
