// src/pages/auth/InscriptionEtablissementPage.tsx
import { useState } from "react";
import { Link } from "react-router-dom";
import { GraduationCap, Building2, User, Mail, Phone, MapPin, Lock, ArrowRight, CheckCircle2, ArrowLeft } from "lucide-react";
import client from "../../api/client";

// ⚠️ La table demandes_compte stocke le formulaire dans une colonne JSON libre
// (`donnees`) — je n'ai pas eu accès au FormRequest exact de
// DemandeController@creerDemandeEtablissement, donc les noms de champs
// ci-dessous sont une hypothèse raisonnable. Si le backend renvoie une 422,
// regarde les erreurs de validation et ajuste les noms de `payload`.

const PAYS = ["Cameroun", "Tchad", "Gabon", "Congo", "RCA", "Guinée équatoriale", "Autre"];

export default function InscriptionEtablissementPage() {
  const [form, setForm] = useState({
    // Établissement
    nom: "", ville: "", pays: "Cameroun", adresse: "",
    email: "", telephone: "",
    // Responsable / futur admin
    responsable_nom: "", responsable_prenom: "", responsable_email: "",
    responsable_telephone: "", mot_de_passe: "", confirmation_mot_de_passe: "",
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [envoye, setEnvoye] = useState(false);

  function set<K extends keyof typeof form>(key: K, value: string) {
    setForm(f => ({ ...f, [key]: value }));
  }

  const motsDePasseValides = form.mot_de_passe.length >= 8 && form.mot_de_passe === form.confirmation_mot_de_passe;
  const formValide = form.nom && form.ville && form.email && form.responsable_nom && form.responsable_prenom
    && form.responsable_email && motsDePasseValides;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!formValide) return;
    setLoading(true);
    setError("");
    try {
      const payload = {
        nom: form.nom, ville: form.ville, pays: form.pays, adresse: form.adresse || undefined,
        email: form.email, telephone: form.telephone || undefined,
        responsable_nom: form.responsable_nom, responsable_prenom: form.responsable_prenom,
        responsable_email: form.responsable_email, responsable_telephone: form.responsable_telephone || undefined,
        mot_de_passe: form.mot_de_passe,
      };
      const res = await client.post("/demandes/etablissement", payload);
      if (res.data.succes ?? true) {
        setEnvoye(true);
      } else {
        setError(res.data.message ?? "Une erreur est survenue.");
      }
    } catch (err: any) {
      const msg = err.response?.data?.message;
      const erreurs = err.response?.data?.donnees ?? err.response?.data?.errors;
      setError(erreurs ? Object.values(erreurs).flat().join(" ") : (msg ?? "Impossible d'envoyer la demande."));
    } finally {
      setLoading(false);
    }
  }

  if (envoye) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4" style={bgStyle}>
        <div className="w-full max-w-md text-center bg-[rgba(17,20,32,0.7)] backdrop-blur-xl border border-[rgba(255,255,255,0.07)] rounded-2xl p-10 shadow-[0_20px_60px_rgba(0,0,0,0.6)]">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[rgba(16,185,129,0.12)] border border-[rgba(16,185,129,0.25)] mb-5">
            <CheckCircle2 size={30} className="text-[#10B981]" />
          </div>
          <h1 className="text-xl font-bold text-[#F0F2FF] mb-2">Demande envoyée</h1>
          <p className="text-sm text-[#8A97B5] leading-relaxed">
            Votre demande pour <span className="text-[#F0F2FF] font-medium">{form.nom}</span> a été transmise.
            Vous recevrez un email à <span className="text-[#F0F2FF] font-medium">{form.responsable_email}</span> dès
            qu'elle sera validée par notre équipe.
          </p>
          <Link to="/login" className="inline-flex items-center gap-2 mt-6 text-sm text-[#7C6AF7] hover:text-[#8B7CFA] font-medium">
            <ArrowLeft size={14} /> Retour à la connexion
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-4 py-10" style={bgStyle}>
      <div className="w-full max-w-xl relative">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[rgba(124,106,247,0.15)] border border-[rgba(124,106,247,0.25)] mb-4 shadow-[0_0_32px_rgba(124,106,247,0.2)]">
            <GraduationCap size={28} className="text-[#7C6AF7]" />
          </div>
          <h1 className="text-2xl font-bold text-[#F0F2FF] tracking-tight">univ<span className="text-[#7C6AF7]">ora</span></h1>
          <p className="text-sm text-[#5C6785] mt-1">Inscrivez votre établissement</p>
        </div>

        <form onSubmit={handleSubmit} className="bg-[rgba(17,20,32,0.7)] backdrop-blur-xl border border-[rgba(255,255,255,0.07)] rounded-2xl p-8 shadow-[0_20px_60px_rgba(0,0,0,0.6)] space-y-6">

          {/* Établissement */}
          <div className="space-y-4">
            <div className="flex items-center gap-2 text-xs font-semibold text-[#8A97B5] uppercase tracking-wide">
              <Building2 size={13} /> Établissement
            </div>
            <Field label="Nom de l'établissement" required value={form.nom} onChange={v => set("nom", v)} placeholder="Ex: Université de Yaoundé I" icon={<Building2 size={15} />} />
            <div className="grid grid-cols-2 gap-3">
              <Field label="Ville" required value={form.ville} onChange={v => set("ville", v)} placeholder="Yaoundé" icon={<MapPin size={15} />} />
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-[#8A97B5] uppercase tracking-wide">Pays</label>
                <select value={form.pays} onChange={e => set("pays", e.target.value)}
                  className="w-full px-4 py-3 bg-[#0A0C14] border border-[rgba(255,255,255,0.08)] rounded-xl text-[#F0F2FF] text-sm outline-none focus:border-[rgba(124,106,247,0.5)]">
                  {PAYS.map(p => <option key={p} value={p}>{p}</option>)}
                </select>
              </div>
            </div>
            <Field label="Adresse" value={form.adresse} onChange={v => set("adresse", v)} placeholder="Optionnel" icon={<MapPin size={15} />} />
            <div className="grid grid-cols-2 gap-3">
              <Field label="Email de l'établissement" required type="email" value={form.email} onChange={v => set("email", v)} placeholder="contact@univ.cm" icon={<Mail size={15} />} />
              <Field label="Téléphone" value={form.telephone} onChange={v => set("telephone", v)} placeholder="+237 6XX XXX XXX" icon={<Phone size={15} />} />
            </div>
          </div>

          <div className="h-px bg-[rgba(255,255,255,0.06)]" />

          {/* Responsable */}
          <div className="space-y-4">
            <div className="flex items-center gap-2 text-xs font-semibold text-[#8A97B5] uppercase tracking-wide">
              <User size={13} /> Compte administrateur
            </div>
            <p className="text-xs text-[#5C6785] -mt-2">Vous serez l'administrateur principal une fois la demande validée.</p>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Prénom" required value={form.responsable_prenom} onChange={v => set("responsable_prenom", v)} icon={<User size={15} />} />
              <Field label="Nom" required value={form.responsable_nom} onChange={v => set("responsable_nom", v)} icon={<User size={15} />} />
            </div>
            <Field label="Email" required type="email" value={form.responsable_email} onChange={v => set("responsable_email", v)} placeholder="vous@univ.cm" icon={<Mail size={15} />} />
            <Field label="Téléphone" value={form.responsable_telephone} onChange={v => set("responsable_telephone", v)} placeholder="Optionnel" icon={<Phone size={15} />} />
            <div className="grid grid-cols-2 gap-3">
              <Field label="Mot de passe" required type="password" value={form.mot_de_passe} onChange={v => set("mot_de_passe", v)} placeholder="8 caractères min." icon={<Lock size={15} />} />
              <Field label="Confirmation" required type="password" value={form.confirmation_mot_de_passe} onChange={v => set("confirmation_mot_de_passe", v)} icon={<Lock size={15} />}
                error={form.confirmation_mot_de_passe && form.mot_de_passe !== form.confirmation_mot_de_passe ? "Ne correspond pas" : undefined} />
            </div>
          </div>

          {error && (
            <div className="flex items-center gap-2 px-4 py-3 bg-[rgba(239,68,68,0.08)] border border-[rgba(239,68,68,0.2)] rounded-xl text-sm text-[#EF4444]">
              <span>⚠</span>{error}
            </div>
          )}

          <button type="submit" disabled={loading || !formValide}
            className="w-full flex items-center justify-center gap-2 py-3 px-6 rounded-xl font-semibold text-sm text-white disabled:opacity-50"
            style={{ background: loading ? "rgba(124,106,247,0.5)" : "linear-gradient(135deg,#7C6AF7,#6355E8)", boxShadow: loading ? "none" : "0 0 24px rgba(124,106,247,0.4)" }}>
            {loading
              ? <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" /></svg>
              : <>Soumettre la demande<ArrowRight size={15} /></>}
          </button>
        </form>

        <p className="text-center text-sm text-[#5C6785] mt-6">
          Déjà un compte ?{" "}
          <Link to="/login" className="text-[#7C6AF7] hover:text-[#8B7CFA] font-medium">Se connecter</Link>
        </p>
      </div>
    </div>
  );
}

const bgStyle = {
  background: "radial-gradient(ellipse at 30% 40%, rgba(124,106,247,0.12) 0%, transparent 55%), radial-gradient(ellipse at 75% 70%, rgba(6,182,212,0.06) 0%, transparent 50%), #0A0C14",
};

function Field({ label, value, onChange, placeholder, icon, type = "text", required, error }: {
  label: string; value: string; onChange: (v: string) => void; placeholder?: string;
  icon?: React.ReactNode; type?: string; required?: boolean; error?: string;
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium text-[#8A97B5] uppercase tracking-wide">
        {label}{required && <span className="text-[#EF4444]"> *</span>}
      </label>
      <div className="relative">
        {icon && <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5C6785]">{icon}</span>}
        <input
          type={type} value={value} onChange={e => onChange(e.target.value)}
          placeholder={placeholder} required={required}
          className={`w-full ${icon ? "pl-10" : "pl-4"} pr-4 py-3 bg-[#0A0C14] border rounded-xl text-[#F0F2FF] text-sm placeholder:text-[#5C6785] outline-none focus:border-[rgba(124,106,247,0.5)] focus:shadow-[0_0_0_3px_rgba(124,106,247,0.1)] ${error ? "border-[#EF4444]" : "border-[rgba(255,255,255,0.08)]"}`}
        />
      </div>
      {error && <p className="text-xs text-[#EF4444]">{error}</p>}
    </div>
  );
}
