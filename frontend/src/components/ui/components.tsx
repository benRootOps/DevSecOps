import { useEffect } from "react";
import { X, TrendingUp, TrendingDown } from "lucide-react";
import { Button } from "./atoms";

function cn(...c: (string|undefined|false|null)[]) { return c.filter(Boolean).join(" "); }

// ── Card ──────────────────────────────────────────────────────
export function Card({ children, className, glow, glass, onClick }: { children: React.ReactNode; className?: string; glow?: boolean; glass?: boolean; onClick?: () => void }) {
  return (
    <div onClick={onClick} className={cn("rounded-xl border transition-all duration-200", glass ? "bg-[rgba(17,20,32,0.6)] backdrop-blur-xl border-[rgba(255,255,255,0.06)]" : "bg-[#111420] border-[rgba(255,255,255,0.06)]", glow && "shadow-[0_0_20px_rgba(124,106,247,0.1)]", onClick && "cursor-pointer hover:border-[rgba(124,106,247,0.3)] hover:shadow-[0_0_24px_rgba(124,106,247,0.12)]", className)}>
      {children}
    </div>
  );
}
export function CardHeader({ children, className }: { children: React.ReactNode; className?: string }) {
  return <div className={cn("px-6 py-4 border-b border-[rgba(255,255,255,0.05)]", className)}>{children}</div>;
}
export function CardBody({ children, className }: { children: React.ReactNode; className?: string }) {
  return <div className={cn("p-6", className)}>{children}</div>;
}

// ── Input ─────────────────────────────────────────────────────
interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> { label?: string; error?: string; icon?: React.ReactNode; }
export function Input({ label, error, icon, className, id, ...props }: InputProps) {
  const inputId = id ?? label?.toLowerCase().replace(/\s/g, "-");
  return (
    <div className="flex flex-col gap-1.5">
      {label && <label htmlFor={inputId} className="text-xs font-medium text-[#8A97B5] uppercase tracking-wide">{label}</label>}
      <div className="relative flex items-center">
        {icon && <span className="absolute left-3 text-[#5C6785]">{icon}</span>}
        <input id={inputId} className={cn("w-full rounded-lg bg-[#0A0C14] border text-[#F0F2FF] text-sm placeholder:text-[#5C6785] outline-none transition-all duration-150 py-2.5", icon ? "pl-10 pr-4" : "px-4", error ? "border-[rgba(239,68,68,0.5)] focus:border-[#EF4444] focus:shadow-[0_0_0_3px_rgba(239,68,68,0.12)]" : "border-[rgba(255,255,255,0.08)] focus:border-[rgba(124,106,247,0.6)] focus:shadow-[0_0_0_3px_rgba(124,106,247,0.12)]", className)} {...props} />
      </div>
      {error && <p className="text-xs text-[#EF4444]">{error}</p>}
    </div>
  );
}

// ── Select ────────────────────────────────────────────────────
interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> { label?: string; error?: string; options: { value: string|number; label: string }[]; }
export function Select({ label, error, options, className, id, ...props }: SelectProps) {
  const selectId = id ?? label?.toLowerCase().replace(/\s/g, "-");
  return (
    <div className="flex flex-col gap-1.5">
      {label && <label htmlFor={selectId} className="text-xs font-medium text-[#8A97B5] uppercase tracking-wide">{label}</label>}
      <select id={selectId} className={cn("w-full rounded-lg bg-[#0A0C14] border text-[#F0F2FF] text-sm px-4 py-2.5 outline-none transition-all duration-150 cursor-pointer", error ? "border-[rgba(239,68,68,0.5)]" : "border-[rgba(255,255,255,0.08)] focus:border-[rgba(124,106,247,0.6)] focus:shadow-[0_0_0_3px_rgba(124,106,247,0.12)]", className)} {...props}>
        {options.map(o => <option key={o.value} value={o.value} className="bg-[#111420]">{o.label}</option>)}
      </select>
      {error && <p className="text-xs text-[#EF4444]">{error}</p>}
    </div>
  );
}

// ── Modal ─────────────────────────────────────────────────────
export function Modal({ open, onClose, title, children, footer, size="md" }: { open: boolean; onClose: () => void; title?: string; children: React.ReactNode; footer?: React.ReactNode; size?: "sm"|"md"|"lg"|"xl" }) {
  useEffect(() => {
    if (!open) return;
    const h = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [open, onClose]);
  if (!open) return null;
  const sizes = { sm:"max-w-sm", md:"max-w-lg", lg:"max-w-2xl", xl:"max-w-4xl" };
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-[rgba(10,12,20,0.8)] backdrop-blur-sm" onClick={onClose} />
      <div className={cn("relative w-full bg-[#111420] rounded-2xl border border-[rgba(255,255,255,0.08)] shadow-[0_20px_60px_rgba(0,0,0,0.7)] animate-[slideUp_0.25s_ease-out]", sizes[size])}>
        {title && <div className="flex items-center justify-between px-6 py-4 border-b border-[rgba(255,255,255,0.06)]"><h3 className="text-base font-semibold text-[#F0F2FF]">{title}</h3><button onClick={onClose} className="text-[#5C6785] hover:text-[#F0F2FF] transition-colors p-1 rounded-lg hover:bg-[rgba(255,255,255,0.06)]"><X size={18}/></button></div>}
        <div className="p-6">{children}</div>
        {footer && <div className="px-6 py-4 border-t border-[rgba(255,255,255,0.06)] flex items-center justify-end gap-3">{footer}</div>}
      </div>
    </div>
  );
}

// ── EmptyState ────────────────────────────────────────────────
export function EmptyState({ icon, title, description, action }: { icon?: React.ReactNode; title: string; description?: string; action?: React.ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 px-8 text-center">
      {icon && <div className="w-16 h-16 rounded-2xl bg-[rgba(124,106,247,0.08)] border border-[rgba(124,106,247,0.15)] flex items-center justify-center text-[#7C6AF7] mb-5">{icon}</div>}
      <h3 className="text-base font-semibold text-[#F0F2FF] mb-2">{title}</h3>
      {description && <p className="text-sm text-[#5C6785] mb-6 max-w-xs">{description}</p>}
      {action}
    </div>
  );
}

// ── StatCard ──────────────────────────────────────────────────
export function StatCard({ label, value, icon, color, trend, loading }: { label: string; value: string|number; icon: React.ReactNode; color: string; trend?: { value: number; label: string }; loading?: boolean }) {
  if (loading) return <div className="bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl p-5 space-y-3"><div className="h-3 w-20 bg-[rgba(255,255,255,0.06)] rounded animate-pulse"/><div className="h-7 w-32 bg-[rgba(255,255,255,0.06)] rounded animate-pulse"/></div>;
  return (
    <div className="bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl p-5 hover:border-[rgba(124,106,247,0.25)] transition-all duration-200 hover:shadow-[0_4px_24px_rgba(0,0,0,0.4)]">
      <div className="flex items-start justify-between mb-4">
        <div className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style={{ background:`${color}15`, color }}>{icon}</div>
        {trend && <div className={cn("flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full", trend.value >= 0 ? "bg-[rgba(16,185,129,0.1)] text-[#10B981]" : "bg-[rgba(239,68,68,0.1)] text-[#EF4444]")}>{trend.value >= 0 ? <TrendingUp size={12}/> : <TrendingDown size={12}/>}{Math.abs(trend.value)}%</div>}
      </div>
      <div className="text-2xl font-bold text-[#F0F2FF] font-mono mb-1">{value}</div>
      <div className="text-xs text-[#5C6785]">{label}</div>
      {trend && <div className="text-xs text-[#5C6785] mt-1">{trend.label}</div>}
    </div>
  );
}
