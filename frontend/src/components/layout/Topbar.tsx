import { useState } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { Search, Bell, LogOut, User, ChevronRight } from "lucide-react";
import { useAuthStore } from "../../stores/authStore";
import client from "../../api/client";

const LABELS: Record<string,string> = { dashboard:"Tableau de bord", utilisateurs:"Utilisateurs", demandes:"Demandes", "emploi-du-temps":"Emploi du temps", presences:"Présences", notes:"Notes", deliberations:"Délibérations", bulletins:"Bulletins", financier:"Financier", abonnements:"Abonnements", profil:"Mon profil" };

function cn(...c: (string|undefined|false|null)[]) { return c.filter(Boolean).join(" "); }

export function Topbar() {
  const { user, clearSession } = useAuthStore();
  const navigate = useNavigate();
  const location = useLocation();
  const [menu, setMenu] = useState(false);
  const [search, setSearch] = useState("");
  const segs = location.pathname.split("/").filter(Boolean);

  const handleLogout = async () => {
    try { await client.post("/auth/logout"); } catch {}
    clearSession(); navigate("/login");
  };

  return (
    <header className="h-16 flex items-center justify-between px-6 border-b border-[rgba(255,255,255,0.06)] bg-[#111420] flex-shrink-0">
      <div className="flex items-center gap-1.5 text-sm">
        <span className="text-[#5C6785]">Univora</span>
        {segs.map((seg, i) => (
          <span key={i} className="flex items-center gap-1.5">
            <ChevronRight size={13} className="text-[#5C6785]"/>
            <span className={i === segs.length-1 ? "text-[#F0F2FF] font-medium" : "text-[#5C6785]"}>{LABELS[seg] ?? seg}</span>
          </span>
        ))}
      </div>
      <div className="flex items-center gap-3">
        <div className="relative hidden md:flex items-center">
          <Search size={14} className="absolute left-3 text-[#5C6785]"/>
          <input value={search} onChange={e=>setSearch(e.target.value)} placeholder="Rechercher…" className="pl-9 pr-4 py-2 text-sm bg-[#0A0C14] border border-[rgba(255,255,255,0.06)] rounded-lg text-[#F0F2FF] placeholder:text-[#5C6785] outline-none w-48 focus:w-64 transition-all focus:border-[rgba(124,106,247,0.4)]"/>
        </div>
        <button className="relative w-9 h-9 flex items-center justify-center rounded-lg text-[#5C6785] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.06)] transition-all">
          <Bell size={17}/><span className="absolute top-1.5 right-1.5 w-2 h-2 bg-[#7C6AF7] rounded-full"/>
        </button>
        <div className="relative">
          <button onClick={() => setMenu(o=>!o)} className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-[rgba(255,255,255,0.06)] transition-all">
            <div className="w-7 h-7 rounded-full bg-[rgba(124,106,247,0.2)] flex items-center justify-center text-[#7C6AF7] text-xs font-bold">{user?.prenom?.[0]}{user?.nom?.[0]}</div>
            <div className="hidden md:block text-left"><div className="text-xs font-semibold text-[#F0F2FF] leading-none">{user?.prenom} {user?.nom}</div><div className="text-[10px] text-[#5C6785] mt-0.5">{user?.role?.nom}</div></div>
          </button>
          {menu && (
            <>
              <div className="fixed inset-0 z-30" onClick={() => setMenu(false)}/>
              <div className="absolute right-0 top-12 z-40 w-48 bg-[#161925] border border-[rgba(255,255,255,0.08)] rounded-xl shadow-[0_8px_32px_rgba(0,0,0,0.6)] overflow-hidden">
                <button onClick={() => { setMenu(false); navigate("/profil"); }} className="w-full flex items-center gap-3 px-4 py-3 text-sm text-[#8A97B5] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.04)] transition-colors"><User size={15}/> Mon profil</button>
                <div className="h-px bg-[rgba(255,255,255,0.06)] mx-3"/>
                <button onClick={handleLogout} className="w-full flex items-center gap-3 px-4 py-3 text-sm text-[#EF4444] hover:bg-[rgba(239,68,68,0.06)] transition-colors"><LogOut size={15}/> Déconnexion</button>
              </div>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
