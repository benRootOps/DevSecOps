import { forwardRef } from "react";

function cn(...c: (string|undefined|false|null)[]) { return c.filter(Boolean).join(" "); }

// ── Button ────────────────────────────────────────────────────
interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary"|"ghost"|"danger"|"success"|"outline";
  size?: "sm"|"md"|"lg";
  loading?: boolean;
  icon?: React.ReactNode;
}
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant="primary", size="md", loading, icon, children, className, disabled, ...props }, ref) => {
    const base = "inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition-all duration-150 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed select-none";
    const variants = {
      primary: "bg-[#7C6AF7] hover:bg-[#8B7CFA] text-white shadow-[0_0_20px_rgba(124,106,247,0.3)] hover:shadow-[0_0_28px_rgba(124,106,247,0.45)]",
      ghost:   "bg-transparent hover:bg-[rgba(124,106,247,0.1)] text-[#8A97B5] hover:text-[#F0F2FF]",
      danger:  "bg-[rgba(239,68,68,0.12)] hover:bg-[rgba(239,68,68,0.2)] text-[#EF4444] border border-[rgba(239,68,68,0.3)]",
      success: "bg-[rgba(16,185,129,0.12)] hover:bg-[rgba(16,185,129,0.2)] text-[#10B981] border border-[rgba(16,185,129,0.3)]",
      outline: "bg-transparent border border-[rgba(255,255,255,0.08)] hover:border-[rgba(124,106,247,0.4)] text-[#F0F2FF] hover:bg-[rgba(124,106,247,0.06)]",
    };
    const sizes = { sm:"px-3 py-1.5 text-xs", md:"px-4 py-2 text-sm", lg:"px-6 py-3 text-base" };
    return (
      <button ref={ref} disabled={disabled||loading} className={cn(base, variants[variant], sizes[size], className)} {...props}>
        {loading ? <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> : icon}
        {children}
      </button>
    );
  }
);
Button.displayName = "Button";

// ── Badge ─────────────────────────────────────────────────────
export function Badge({ children, color="#7C6AF7", className }: { children: React.ReactNode; color?: string; className?: string }) {
  return <span className={cn("inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold", className)} style={{ background:`${color}18`, color, border:`1px solid ${color}35` }}>{children}</span>;
}

// ── Skeleton ──────────────────────────────────────────────────
export function Skeleton({ className }: { className?: string }) {
  return <div className={cn("rounded-lg bg-[rgba(255,255,255,0.06)] animate-pulse", className)} />;
}

// ── Avatar ────────────────────────────────────────────────────
export function Avatar({ src, nom, prenom, size="md" }: { src?: string|null; nom?: string; prenom?: string; size?: "sm"|"md"|"lg"|"xl" }) {
  const sizes = { sm:"w-7 h-7 text-xs", md:"w-9 h-9 text-sm", lg:"w-12 h-12 text-base", xl:"w-16 h-16 text-xl" };
  const initiales = [prenom?.[0], nom?.[0]].filter(Boolean).join("").toUpperCase() || "?";
  if (src) return <img src={src} alt="" className={cn("rounded-full object-cover ring-2 ring-[rgba(124,106,247,0.3)]", sizes[size])} />;
  return <div className={cn("rounded-full flex items-center justify-center font-semibold flex-shrink-0 bg-[rgba(124,106,247,0.2)] text-[#7C6AF7] ring-2 ring-[rgba(124,106,247,0.25)]", sizes[size])}>{initiales}</div>;
}
