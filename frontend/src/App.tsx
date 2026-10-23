import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AppShell } from './components/layout/AppShell';
import { OverviewPage } from './pages/OverviewPage';
import { PolicyListPage } from './pages/PolicyListPage';
import { NewPolicyPage } from './pages/NewPolicyPage';
import { PolicyDetailPage } from './pages/PolicyDetailPage';
import { OperationsPage } from './pages/OperationsPage';
import { NotFoundPage } from './pages/NotFoundPage';

export const App: React.FC = () => {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<AppShell />}>
          <Route index element={<OverviewPage />} />
          <Route path="policies" element={<PolicyListPage />} />
          <Route path="policies/new" element={<NewPolicyPage />} />
          <Route path="policies/:id" element={<PolicyDetailPage />} />
          <Route path="operations" element={<OperationsPage />} />
          <Route path="*" element={<NotFoundPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
};

export default App;
