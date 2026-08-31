import { NavLink } from "react-router-dom";
import { LayoutDashboard, Users, Calendar, CheckSquare, BookOpen, Award, FileText, DollarSign, CreditCard, ClipboardList, GraduationCap, ChevronLeft, ChevronRight, Building2, UserSquare2 } from "lucide-react";
import { useAuthStore } from "../../stores/authStore";

function cn(...c: (string|undefined|false|null)[]) { return c.filter(Boolean).join(" "); }

const NAV_GROUPS = [
  { label:"Général", items:[
    { to:"/dashboard",  label:"Tableau de bord", icon:LayoutDashboard, color:"#7C6AF7" },
    { to:"/demandes",   label:"Demandes",         icon:ClipboardList,   color:"#F97316", permission:"demandes.voir" },
  ]},
  { label:"Académique", items:[
    { to:"/emploi-du-temps", label:"Emploi du temps", icon:Calendar,    color:"#06B6D4", permission:"emploi_temps.voir" },
    { to:"/presences",       label:"Présences",        icon:CheckSquare, color:"#10B981", permission:"presences.voir" },
    { to:"/notes",           label:"Notes",             icon:BookOpen,   color:"#A78BFA", permission:"notes.voir" },
    { to:"/deliberations",   label:"Délibérations",    icon:Award,      color:"#EC4899", permission:"deliberations.voir" },
    { to:"/bulletins",       label:"Bulletins",         icon:FileText,   color:"#14B8A6", permission:"bulletins.voir" },
  ]},
  { label:"Administration", items:[
    { to:"/utilisateurs",   label:"Utilisateurs",   icon:Users,         color:"#7C6AF7", permission:"utilisateurs.voir" },
    { to:"/enseignants",    label:"Enseignants",    icon:GraduationCap, color:"#A78BFA", permission:"enseignants.voir" },
    { to:"/etudiants",      label:"Étudiants",      icon:UserSquare2,   color:"#06B6D4", permission:"etudiants.voir" },
    { to:"/etablissements", label:"Établissements", icon:Building2,     color:"#8B5CF6", permission:"etablissements.voir" },
    { to:"/financier",      label:"Financier",       icon:DollarSign,   color:"#F59E0B", permission:"financier.voir" },
  ]},
  { label:"SaaS", items:[
    { to:"/abonnements", label:"Abonnements", icon:CreditCard, color:"#6366F1", permission:"abonnements.voir" },
  ]},
];

export function Sidebar({ collapsed, onToggle }: { collapsed: boolean; onToggle: () => void }) {
  const { user, can } = useAuthStore();
  return (
    <aside className={cn("h-screen flex flex-col bg-[#111420] border-r border-[rgba(255,255,255,0.06)] transition-all duration-300 ease-in-out flex-shrink-0 relative", collapsed ? "w-[68px]" : "w-[220px]")}>
      <div className={cn("flex items-center border-b border-[rgba(255,255,255,0.06)] h-16 flex-shrink-0", collapsed ? "justify-center px-3" : "px-5 gap-3")}>
        <div className="w-8 h-8 rounded-lg bg-[#7C6AF7] flex items-center justify-center flex-shrink-0 shadow-[0_0_16px_rgba(124,106,247,0.4)]"><GraduationCap size={16} className="text-white"/></div>
        {!collapsed && <span className="text-[#F0F2FF] font-bold text-base tracking-tight">univ<span className="text-[#7C6AF7]">ora</span></span>}
      </div>
      <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-6 scrollbar-hide">
        {NAV_GROUPS.map(group => {
          const items = group.items.filter(i => !i.permission || can(i.permission));
          if (!items.length) return null;
          return (
            <div key={group.label}>
              {!collapsed && <div className="px-3 mb-2 text-[10px] font-semibold text-[#5C6785] uppercase tracking-[0.1em]">{group.label}</div>}
              <ul className="space-y-0.5">
                {items.map(item => (
                  <li key={item.to}>
                    <NavLink to={item.to} className={({ isActive }) => cn("flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all duration-150 group relative", isActive ? "bg-[rgba(124,106,247,0.12)] text-[#F0F2FF]" : "text-[#8A97B5] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.04)]", collapsed && "justify-center px-2")}>
                      {({ isActive }) => (
                        <>
                          {isActive && <span className="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-5 rounded-r-full" style={{ background: item.color }}/>}
                          <item.icon size={17} style={{ color: isActive ? item.color : undefined }}/>
                          {!collapsed && <span className="truncate font-medium text-[13px]">{item.label}</span>}
                          {collapsed && <div className="absolute left-full ml-3 px-2 py-1 bg-[#161925] border border-[rgba(255,255,255,0.08)] rounded-lg text-xs text-[#F0F2FF] whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-[0_4px_16px_rgba(0,0,0,0.5)]">{item.label}</div>}
                        </>
                      )}
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          );
        })}
      </nav>
      <div className={cn("border-t border-[rgba(255,255,255,0.06)] p-3 flex items-center gap-3 flex-shrink-0", collapsed && "justify-center")}>
        <div className="w-7 h-7 rounded-full bg-[rgba(124,106,247,0.2)] flex items-center justify-center text-[#7C6AF7] text-xs font-bold flex-shrink-0">{user?.prenom?.[0]}{user?.nom?.[0]}</div>
        {!collapsed && <div className="flex-1 min-w-0"><div className="text-xs font-semibold text-[#F0F2FF] truncate">{user?.prenom} {user?.nom}</div><div className="text-[10px] text-[#5C6785] truncate">{user?.role?.nom}</div></div>}
      </div>
      <button onClick={onToggle} className="absolute -right-3 top-20 w-6 h-6 rounded-full flex items-center justify-center bg-[#111420] border border-[rgba(255,255,255,0.1)] text-[#5C6785] hover:text-[#F0F2FF] hover:border-[rgba(124,106,247,0.4)] transition-all duration-150 shadow-[0_2px_8px_rgba(0,0,0,0.4)] z-10">
        {collapsed ? <ChevronRight size={12}/> : <ChevronLeft size={12}/>}
      </button>
    </aside>
  );
}
