// src/pages/utilisateurs/ProfilPage.tsx
import { useRef, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { User, Camera, Lock, Mail, ShieldCheck, ShieldAlert, Building2 } from "lucide-react";
import { Card, CardHeader, CardBody, Input } from "../../components/ui/components";
import { Button, Avatar, Badge } from "../../components/ui/atoms";
import { useAuthStore } from "../../stores/authStore";
import { toast } from "sonner";
import client from "../../api/client";

export default function ProfilPage() {
  const { user, session, setSession } = useAuthStore();
  const fileRef = useRef<HTMLInputElement>(null);
  const [pwd, setPwd] = useState({ ancien_mot_de_passe: "", nouveau_mot_de_passe: "", confirmation: "" });

  const photoMut = useMutation({
    mutationFn: (file: File) => {
      const fd = new FormData();
      fd.append("photo", file);
      return client.post(`/utilisateurs/${user?.id}/photo`, fd, { headers: { "Content-Type": "multipart/form-data" } });
    },
    onSuccess: async (res) => {
      toast.success("Photo mise à jour.");
      const nouveauUser = res.data?.donnees ?? { ...user, photo_url: res.data?.donnees?.photo_url };
      if (session) setSession({ ...session, utilisateur: { ...user!, ...nouveauUser } });
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur lors de l'envoi de la photo"),
  });

  const pwdMut = useMutation({
    mutationFn: (d: typeof pwd) => client.patch("/utilisateurs/changer-mot-de-passe", d),
    onSuccess: () => {
      toast.success("Mot de passe modifié.");
      setPwd({ ancien_mot_de_passe: "", nouveau_mot_de_passe: "", confirmation: "" });
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const verifMut = useMutation({
    mutationFn: () => client.post(`/utilisateurs/${user?.id}/envoyer-verification`),
    onSuccess: () => toast.success("Email de vérification envoyé."),
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (file) photoMut.mutate(file);
  }

  const pwdValid = pwd.ancien_mot_de_passe && pwd.nouveau_mot_de_passe.length >= 8 && pwd.nouveau_mot_de_passe === pwd.confirmation;

  return (
    <div className="space-y-6 max-w-2xl">
      <div>
        <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
          <User size={20} className="text-[#7C6AF7]" /> Mon profil
        </h1>
        <p className="text-sm text-[#5C6785] mt-0.5">Informations personnelles et sécurité</p>
      </div>

      {/* Carte identité */}
      <Card>
        <CardBody>
          <div className="flex items-center gap-5">
            <div className="relative group">
              <Avatar src={user?.photo_url} nom={user?.nom} prenom={user?.prenom} size="xl" />
              <button
                onClick={() => fileRef.current?.click()}
                disabled={photoMut.isPending}
                className="absolute inset-0 rounded-full bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity"
              >
                <Camera size={18} className="text-white" />
              </button>
              <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={handleFileChange} />
            </div>
            <div className="flex-1 min-w-0">
              <div className="text-lg font-semibold text-[#F0F2FF]">{user?.prenom} {user?.nom}</div>
              <div className="text-sm text-[#8A97B5]">{user?.role?.nom}</div>
              <div className="flex items-center gap-2 mt-2 flex-wrap">
                <span className="inline-flex items-center gap-1.5 text-xs text-[#5C6785]">
                  <Mail size={12} /> {user?.email}
                </span>
                {user?.email_verifie ? (
                  <Badge color="#10B981"><span className="inline-flex items-center gap-1"><ShieldCheck size={11} /> Vérifié</span></Badge>
                ) : (
                  <Badge color="#F59E0B"><span className="inline-flex items-center gap-1"><ShieldAlert size={11} /> Non vérifié</span></Badge>
                )}
              </div>
              {user?.etablissement && (
                <div className="flex items-center gap-1.5 text-xs text-[#5C6785] mt-1.5">
                  <Building2 size={12} /> {user.etablissement.nom} — {user.etablissement.ville}
                </div>
              )}
            </div>
          </div>
          {!user?.email_verifie && (
            <div className="mt-4">
              <Button variant="outline" size="sm" loading={verifMut.isPending} onClick={() => verifMut.mutate()}>
                Envoyer l'email de vérification
              </Button>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Informations */}
      <Card>
        <CardHeader><span className="text-sm font-semibold text-[#F0F2FF]">Informations</span></CardHeader>
        <CardBody>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <div className="text-[10px] font-medium text-[#5C6785] uppercase tracking-wide mb-1">Téléphone</div>
              <div className="text-[#F0F2FF]">{user?.telephone ?? "—"}</div>
            </div>
            <div>
              <div className="text-[10px] font-medium text-[#5C6785] uppercase tracking-wide mb-1">Genre</div>
              <div className="text-[#F0F2FF]">{user?.genre ?? "—"}</div>
            </div>
            <div>
              <div className="text-[10px] font-medium text-[#5C6785] uppercase tracking-wide mb-1">Date de naissance</div>
              <div className="text-[#F0F2FF]">{user?.date_naissance ?? "—"}</div>
            </div>
            <div>
              <div className="text-[10px] font-medium text-[#5C6785] uppercase tracking-wide mb-1">Dernière connexion</div>
              <div className="text-[#F0F2FF]">{user?.derniere_connexion ? new Date(user.derniere_connexion).toLocaleString("fr-FR") : "—"}</div>
            </div>
          </div>
          <p className="text-xs text-[#5C6785] bg-[rgba(124,106,247,0.06)] border border-[rgba(124,106,247,0.15)] rounded-lg px-3 py-2 mt-4">
            💡 Pour modifier ces informations, contactez l'administrateur de votre établissement.
          </p>
        </CardBody>
      </Card>

      {/* Sécurité */}
      <Card>
        <CardHeader>
          <span className="text-sm font-semibold text-[#F0F2FF] flex items-center gap-2"><Lock size={14} /> Changer le mot de passe</span>
        </CardHeader>
        <CardBody>
          <div className="space-y-4">
            <Input label="Mot de passe actuel" type="password" value={pwd.ancien_mot_de_passe}
              onChange={e => setPwd(p => ({ ...p, ancien_mot_de_passe: e.target.value }))} />
            <Input label="Nouveau mot de passe" type="password" value={pwd.nouveau_mot_de_passe}
              onChange={e => setPwd(p => ({ ...p, nouveau_mot_de_passe: e.target.value }))} />
            <Input label="Confirmer le nouveau mot de passe" type="password" value={pwd.confirmation}
              error={pwd.confirmation && pwd.nouveau_mot_de_passe !== pwd.confirmation ? "Les mots de passe ne correspondent pas" : undefined}
              onChange={e => setPwd(p => ({ ...p, confirmation: e.target.value }))} />
            <Button loading={pwdMut.isPending} disabled={!pwdValid} onClick={() => pwdMut.mutate(pwd)}>
              Mettre à jour le mot de passe
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
