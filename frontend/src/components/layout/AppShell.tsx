import { useState } from "react";
import { Outlet, Navigate } from "react-router-dom";
import { Sidebar } from "./Sidebar";
import { Topbar } from "./Topbar";
import { useAuthStore } from "../../stores/authStore";

export function AppShell() {
  const { isAuthenticated } = useAuthStore();
  const [collapsed, setCollapsed] = useState(false);
  if (!isAuthenticated) return <Navigate to="/login" replace/>;
  return (
    <div className="flex h-screen overflow-hidden" style={{ background:"radial-gradient(ellipse at 20% 50%, rgba(124,106,247,0.05) 0%, transparent 60%), radial-gradient(ellipse at 80% 20%, rgba(6,182,212,0.03) 0%, transparent 50%), #0A0C14" }}>
      <div className="relative"><Sidebar collapsed={collapsed} onToggle={() => setCollapsed(c=>!c)}/></div>
      <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
        <Topbar/>
        <main className="flex-1 overflow-y-auto p-6"><Outlet/></main>
      </div>
    </div>
  );
}

export function PermissionGate({ permission, fallback=null, children }: { permission: string; fallback?: React.ReactNode; children: React.ReactNode }) {
  const { can } = useAuthStore();
  return can(permission) ? <>{children}</> : <>{fallback}</>;
}
