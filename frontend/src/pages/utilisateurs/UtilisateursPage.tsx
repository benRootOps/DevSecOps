// src/pages/utilisateurs/UtilisateursPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Users, Plus, Search, MoreVertical, UserCheck, UserX, RefreshCw } from "lucide-react";
import { useAuthStore } from "../../stores/authStore";
import { Card, CardHeader, CardBody } from "../../components/ui/components";
import { Button } from "../../components/ui/atoms";
import { Badge } from "../../components/ui/atoms";
import { Avatar } from "../../components/ui/atoms";
import { Skeleton } from "../../components/ui/atoms";
import { EmptyState } from "../../components/ui/components";
import { Modal } from "../../components/ui/components";
import { Input, Select } from "../../components/ui/components";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";
import { cn } from "../../utils/cn";

const ROLES_OPTIONS = [
  { value: "", label: "Tous les rôles" },
  { value: "2", label: "Admin Universitaire" },
  { value: "3", label: "Secrétaire" },
  { value: "4", label: "Comptable" },
  { value: "5", label: "Enseignant" },
  { value: "6", label: "Étudiant" },
];

const ROLE_COLORS: Record<string, string> = {
  super_admin:           "#7C6AF7",
  admin_universitaire:   "#06B6D4",
  secretaire_academique: "#10B981",
  comptable:             "#F59E0B",
  enseignant:            "#A78BFA",
  etudiant:              "#EC4899",
};

export default function UtilisateursPage() {
  const { can } = useAuthStore();
  const qc = useQueryClient();

  const [search,   setSearch]   = useState("");
  const [roleId,   setRoleId]   = useState("");
  const [page,     setPage]     = useState(1);
  const [modal,    setModal]    = useState(false);
  const [menuId,   setMenuId]   = useState<number | null>(null);

  const [form, setForm] = useState({
    nom: "", prenom: "", email: "", role_id: "5",
    telephone: "", genre: "", specialite: "",
  });

  const { data, isLoading } = useQuery({
    queryKey: ["utilisateurs", search, roleId, page],
    queryFn: () =>
      client.get("/utilisateurs", {
        params: { recherche: search || undefined, role_id: roleId || undefined, page, par_page: 15 },
      }).then(r => r.data.donnees),
  });

  const createMut = useMutation({
    mutationFn: (d: typeof form) => client.post("/utilisateurs", d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["utilisateurs"] });
      setModal(false);
      setForm({ nom: "", prenom: "", email: "", role_id: "5", telephone: "", genre: "", specialite: "" });
      toast.success("Compte créé. Les identifiants ont été envoyés par email.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const toggleMut = useMutation({
    mutationFn: (id: number) => client.patch(`/utilisateurs/${id}/toggle-actif`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["utilisateurs"] });
      toast.success("Statut mis à jour.");
    },
  });

  const resetMut = useMutation({
    mutationFn: (id: number) => client.patch(`/utilisateurs/${id}/reinitialiser-mdp`),
    onSuccess: () => toast.success("Mot de passe réinitialisé. Email envoyé."),
  });

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <Users size={20} className="text-[#7C6AF7]" />
            Utilisateurs
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">
            {data?.total ?? "—"} membre{data?.total !== 1 ? "s" : ""} au total
          </p>
        </div>
        <PermissionGate permission="utilisateurs.creer">
          <Button icon={<Plus size={15} />} onClick={() => setModal(true)}>
            Nouveau compte
          </Button>
        </PermissionGate>
      </div>

      {/* Filtres */}
      <div className="flex items-center gap-3 flex-wrap">
        <div className="relative flex-1 min-w-[200px] max-w-xs">
          <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#5C6785]" />
          <input
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1); }}
            placeholder="Rechercher un utilisateur…"
            className="w-full pl-9 pr-4 py-2 text-sm bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-lg text-[#F0F2FF] placeholder:text-[#5C6785] outline-none focus:border-[rgba(124,106,247,0.4)]"
          />
        </div>
        <select
          value={roleId}
          onChange={e => { setRoleId(e.target.value); setPage(1); }}
          className="px-3 py-2 text-sm bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-lg text-[#8A97B5] outline-none focus:border-[rgba(124,106,247,0.4)]"
        >
          {ROLES_OPTIONS.map(o => (
            <option key={o.value} value={o.value} className="bg-[#111420]">{o.label}</option>
          ))}
        </select>
      </div>

      {/* Table */}
      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Utilisateur", "Rôle", "Email", "Statut", "Connexion", ""].map(h => (
                  <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 8 }).map((_, i) => (
                    <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                      {Array.from({ length: 6 }).map((_, j) => (
                        <td key={j} className="px-6 py-4">
                          <Skeleton className="h-4 w-full rounded" />
                        </td>
                      ))}
                    </tr>
                  ))
                : data?.data?.length === 0
                ? (
                  <tr>
                    <td colSpan={6}>
                      <EmptyState
                        icon={<Users size={24} />}
                        title="Aucun utilisateur"
                        description="Créez le premier compte pour commencer."
                      />
                    </td>
                  </tr>
                )
                : data?.data?.map((u: any) => (
                  <tr
                    key={u.id}
                    className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors group"
                  >
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <Avatar src={u.photo_url} nom={u.nom} prenom={u.prenom} size="sm" />
                        <div>
                          <div className="text-sm font-medium text-[#F0F2FF]">{u.prenom} {u.nom}</div>
                          <div className="text-xs text-[#5C6785]">#{u.id}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <Badge color={ROLE_COLORS[u.role?.code] ?? "#7C6AF7"}>
                        {u.role?.nom ?? "—"}
                      </Badge>
                    </td>
                    <td className="px-6 py-4 text-sm text-[#8A97B5] font-mono">{u.email}</td>
                    <td className="px-6 py-4">
                      <span className={cn(
                        "inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full",
                        u.est_actif
                          ? "bg-[rgba(16,185,129,0.1)] text-[#10B981]"
                          : "bg-[rgba(239,68,68,0.1)] text-[#EF4444]"
                      )}>
                        <span className={cn("w-1.5 h-1.5 rounded-full", u.est_actif ? "bg-[#10B981]" : "bg-[#EF4444]")} />
                        {u.est_actif ? "Actif" : "Inactif"}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-xs text-[#5C6785]">
                      {u.derniere_connexion
                        ? new Date(u.derniere_connexion).toLocaleDateString("fr-FR")
                        : "Jamais"}
                    </td>
                    <td className="px-6 py-4">
                      <div className="relative flex justify-end">
                        <button
                          onClick={() => setMenuId(menuId === u.id ? null : u.id)}
                          className="opacity-0 group-hover:opacity-100 p-1.5 rounded-lg text-[#5C6785] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.06)] transition-all"
                        >
                          <MoreVertical size={15} />
                        </button>
                        {menuId === u.id && (
                          <>
                            <div className="fixed inset-0 z-30" onClick={() => setMenuId(null)} />
                            <div className="absolute right-0 top-8 z-40 w-44 bg-[#161925] border border-[rgba(255,255,255,0.08)] rounded-xl shadow-[0_8px_32px_rgba(0,0,0,0.6)] overflow-hidden">
                              <PermissionGate permission="utilisateurs.modifier">
                                <button
                                  onClick={() => { toggleMut.mutate(u.id); setMenuId(null); }}
                                  className="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs text-[#8A97B5] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.04)] transition-colors"
                                >
                                  {u.est_actif ? <><UserX size={13} /> Désactiver</> : <><UserCheck size={13} /> Activer</>}
                                </button>
                                <button
                                  onClick={() => { resetMut.mutate(u.id); setMenuId(null); }}
                                  className="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs text-[#8A97B5] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.04)] transition-colors"
                                >
                                  <RefreshCw size={13} /> Réinitialiser mdp
                                </button>
                              </PermissionGate>
                            </div>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {data && data.last_page > 1 && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-[rgba(255,255,255,0.05)]">
            <span className="text-xs text-[#5C6785]">
              Page {data.current_page} sur {data.last_page} · {data.total} résultats
            </span>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage(p => p - 1)}>
                ← Précédent
              </Button>
              <Button variant="outline" size="sm" disabled={page === data.last_page} onClick={() => setPage(p => p + 1)}>
                Suivant →
              </Button>
            </div>
          </div>
        )}
      </Card>

      {/* Modal création */}
      <Modal
        open={modal}
        onClose={() => setModal(false)}
        title="Créer un compte"
        size="md"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModal(false)}>Annuler</Button>
            <Button loading={createMut.isPending} onClick={() => createMut.mutate(form)}>
              Créer le compte
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Input label="Prénom" value={form.prenom} onChange={e => setForm(f => ({ ...f, prenom: e.target.value }))} required />
            <Input label="Nom" value={form.nom} onChange={e => setForm(f => ({ ...f, nom: e.target.value }))} required />
          </div>
          <Input label="Email" type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} required />
          <Input label="Téléphone" value={form.telephone} onChange={e => setForm(f => ({ ...f, telephone: e.target.value }))} />
          <Select
            label="Rôle"
            value={form.role_id}
            onChange={e => setForm(f => ({ ...f, role_id: e.target.value }))}
            options={ROLES_OPTIONS.filter(o => o.value).map(o => ({ value: o.value, label: o.label }))}
          />
          {form.role_id === "5" && (
            <Input label="Spécialité" value={form.specialite} onChange={e => setForm(f => ({ ...f, specialite: e.target.value }))} placeholder="Ex: Informatique" />
          )}
          <p className="text-xs text-[#5C6785] bg-[rgba(124,106,247,0.06)] border border-[rgba(124,106,247,0.15)] rounded-lg px-3 py-2">
            💡 Un mot de passe temporaire sera généré et envoyé automatiquement par email.
          </p>
        </div>
      </Modal>
    </div>
  );
}
