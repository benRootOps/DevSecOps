import { useState } from "react";
import { useNavigate, Navigate, Link } from "react-router-dom";
import { GraduationCap, Mail, Lock, Eye, EyeOff, ArrowRight } from "lucide-react";
import { useAuthStore } from "../../stores/authStore";
import client from "../../api/client";

export default function LoginPage() {
  const { isAuthenticated, setSession } = useAuthStore();
  const navigate = useNavigate();
  const [email,setEmail]=useState("superadmin@univora.cm"); const [password,setPassword]=useState("Univora@2026!");
  const [showPwd,setShowPwd]=useState(false); const [loading,setLoading]=useState(false); const [error,setError]=useState("");
  if (isAuthenticated) return <Navigate to="/dashboard" replace/>;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault(); setLoading(true); setError("");
    try {
      const res = await client.post("/auth/login", { email, mot_de_passe: password });
      if (res.data.succes) { setSession(res.data.donnees); navigate("/dashboard"); }
      else setError(res.data.message);
    } catch (err: any) { setError(err.response?.data?.message ?? "Erreur de connexion."); }
    finally { setLoading(false); }
  };

  return (
    <div className="min-h-screen flex items-center justify-center p-4" style={{ background:"radial-gradient(ellipse at 30% 40%, rgba(124,106,247,0.12) 0%, transparent 55%), radial-gradient(ellipse at 75% 70%, rgba(6,182,212,0.06) 0%, transparent 50%), #0A0C14" }}>
      <div className="w-full max-w-md relative">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[rgba(124,106,247,0.15)] border border-[rgba(124,106,247,0.25)] mb-4 shadow-[0_0_32px_rgba(124,106,247,0.2)]"><GraduationCap size={28} className="text-[#7C6AF7]"/></div>
          <h1 className="text-2xl font-bold text-[#F0F2FF] tracking-tight">univ<span className="text-[#7C6AF7]">ora</span></h1>
          <p className="text-sm text-[#5C6785] mt-1">Connectez-vous à votre espace</p>
        </div>
        <div className="bg-[rgba(17,20,32,0.7)] backdrop-blur-xl border border-[rgba(255,255,255,0.07)] rounded-2xl p-8 shadow-[0_20px_60px_rgba(0,0,0,0.6)]">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-[#8A97B5] uppercase tracking-wide">Email</label>
              <div className="relative"><Mail size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5C6785]"/>
                <input type="email" value={email} onChange={e=>setEmail(e.target.value)} placeholder="votre@email.com" required autoFocus className="w-full pl-10 pr-4 py-3 bg-[#0A0C14] border border-[rgba(255,255,255,0.08)] rounded-xl text-[#F0F2FF] text-sm placeholder:text-[#5C6785] outline-none focus:border-[rgba(124,106,247,0.5)] focus:shadow-[0_0_0_3px_rgba(124,106,247,0.1)]"/>
              </div>
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-[#8A97B5] uppercase tracking-wide">Mot de passe</label>
              <div className="relative"><Lock size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5C6785]"/>
                <input type={showPwd?"text":"password"} value={password} onChange={e=>setPassword(e.target.value)} placeholder="••••••••" required className="w-full pl-10 pr-10 py-3 bg-[#0A0C14] border border-[rgba(255,255,255,0.08)] rounded-xl text-[#F0F2FF] text-sm placeholder:text-[#5C6785] outline-none focus:border-[rgba(124,106,247,0.5)] focus:shadow-[0_0_0_3px_rgba(124,106,247,0.1)]"/>
                <button type="button" onClick={()=>setShowPwd(v=>!v)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-[#5C6785] hover:text-[#8A97B5]">{showPwd?<EyeOff size={15}/>:<Eye size={15}/>}</button>
              </div>
            </div>
            {error && <div className="flex items-center gap-2 px-4 py-3 bg-[rgba(239,68,68,0.08)] border border-[rgba(239,68,68,0.2)] rounded-xl text-sm text-[#EF4444]"><span>⚠</span>{error}</div>}
            <button type="submit" disabled={loading} className="w-full flex items-center justify-center gap-2 py-3 px-6 rounded-xl font-semibold text-sm text-white disabled:opacity-60" style={{ background:loading?"rgba(124,106,247,0.5)":"linear-gradient(135deg,#7C6AF7,#6355E8)", boxShadow:loading?"none":"0 0 24px rgba(124,106,247,0.4)" }}>
              {loading?<svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>:<>Se connecter<ArrowRight size={15}/></>}
            </button>
          </form>
        </div>
        <p className="text-center text-sm text-[#5C6785] mt-6">
          Votre université n'a pas encore de compte ?{" "}
          <Link to="/inscription" className="text-[#7C6AF7] hover:text-[#8B7CFA] font-medium">
            Inscrire votre établissement
          </Link>
        </p>
      </div>
    </div>
  );
}
