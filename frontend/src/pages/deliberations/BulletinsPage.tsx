// src/pages/deliberations/BulletinsPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { FileText, Users, User, Send, Eye } from "lucide-react";
import { Card, Modal, EmptyState, Input } from "../../components/ui/components";
import { Button, Badge, Skeleton, Avatar } from "../../components/ui/atoms";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";

// ⚠️ Champs déduits du schéma (bulletins) — à ajuster selon le
// FormRequest exact de BulletinController si différent.

export default function BulletinsPage() {
  const qc = useQueryClient();
  const [modalClasse, setModalClasse] = useState(false);
  const [modalEtudiant, setModalEtudiant] = useState(false);
  const [formClasse, setFormClasse] = useState({ classe_id: "1", session_examen_id: "1" });
  const [formEtudiant, setFormEtudiant] = useState({ etudiant_id: "", session_examen_id: "1" });

  const { data: bulletins, isLoading } = useQuery({
    queryKey: ["bulletins"],
    queryFn: () => client.get("/bulletins").then(r => r.data.donnees),
  });

  const genererClasseMut = useMutation({
    mutationFn: (d: typeof formClasse) => client.post("/bulletins/generer-classe", {
      classe_id: Number(d.classe_id), session_examen_id: Number(d.session_examen_id),
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["bulletins"] });
      setModalClasse(false);
      toast.success("Bulletins générés pour la classe.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const genererEtudiantMut = useMutation({
    mutationFn: (d: typeof formEtudiant) => client.post("/bulletins/generer-etudiant", {
      etudiant_id: Number(d.etudiant_id), session_examen_id: Number(d.session_examen_id),
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["bulletins"] });
      setModalEtudiant(false);
      setFormEtudiant({ etudiant_id: "", session_examen_id: "1" });
      toast.success("Bulletin généré.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const publierMut = useMutation({
    mutationFn: (id: number) => client.patch(`/bulletins/${id}/publier`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["bulletins"] }); toast.success("Bulletin publié."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const rows: any[] = bulletins ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <FileText size={20} className="text-[#14B8A6]" /> Bulletins
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">Génération et publication des bulletins</p>
        </div>
        <PermissionGate permission="bulletins.generer">
          <div className="flex gap-2">
            <Button variant="outline" icon={<Users size={15} />} onClick={() => setModalClasse(true)}>
              Générer pour une classe
            </Button>
            <Button icon={<User size={15} />} onClick={() => setModalEtudiant(true)}>
              Générer pour un étudiant
            </Button>
          </div>
        </PermissionGate>
      </div>

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Étudiant", "Matricule", "Session", "Moyenne", "Statut", ""].map(h => (
                  <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 6 }).map((_, i) => (
                    <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                      {[1, 2, 3, 4, 5, 6].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                    </tr>
                  ))
                : rows.length === 0
                ? <tr><td colSpan={6}><EmptyState icon={<FileText size={24} />} title="Aucun bulletin" description="Générez les bulletins pour une classe ou un étudiant." /></td></tr>
                : rows.map((b: any) => {
                    const publie = b.statut === "publie" || b.publie === true;
                    return (
                      <tr key={b.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                        <td className="px-6 py-3">
                          <div className="flex items-center gap-3">
                            <Avatar nom={b.etudiant?.nom} prenom={b.etudiant?.prenom} size="sm" />
                            <span className="text-sm text-[#F0F2FF]">{b.etudiant?.prenom} {b.etudiant?.nom}</span>
                          </div>
                        </td>
                        <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{b.etudiant?.matricule}</td>
                        <td className="px-6 py-3 text-xs text-[#5C6785]">{b.session_examen?.nom ?? b.session_examen_id}</td>
                        <td className="px-6 py-3 text-sm font-mono font-bold" style={{ color: Number(b.moyenne_generale ?? 0) >= 10 ? "#10B981" : "#EF4444" }}>
                          {b.moyenne_generale ?? "—"} / 20
                        </td>
                        <td className="px-6 py-3">
                          <Badge color={publie ? "#10B981" : "#F59E0B"}>{publie ? "Publié" : "Brouillon"}</Badge>
                        </td>
                        <td className="px-6 py-3 text-right">
                          <div className="flex justify-end gap-2">
                            {b.url ?? b.fichier_url ? (
                              <a href={b.url ?? b.fichier_url} target="_blank" rel="noreferrer">
                                <Button variant="ghost" size="sm" icon={<Eye size={13} />}>Voir</Button>
                              </a>
                            ) : null}
                            <PermissionGate permission="bulletins.publier">
                              {!publie && (
                                <Button variant="outline" size="sm" icon={<Send size={13} />}
                                  loading={publierMut.isPending} onClick={() => publierMut.mutate(b.id)}>
                                  Publier
                                </Button>
                              )}
                            </PermissionGate>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
            </tbody>
          </table>
        </div>
      </Card>

      {/* Générer pour une classe */}
      <Modal open={modalClasse} onClose={() => setModalClasse(false)} title="Générer les bulletins d'une classe" size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModalClasse(false)}>Annuler</Button>
            <Button loading={genererClasseMut.isPending} onClick={() => genererClasseMut.mutate(formClasse)}>Générer</Button>
          </>
        }>
        <div className="space-y-4">
          <Input label="Classe ID" type="number" value={formClasse.classe_id}
            onChange={e => setFormClasse(f => ({ ...f, classe_id: e.target.value }))} />
          <Input label="Session d'examen ID" type="number" value={formClasse.session_examen_id}
            onChange={e => setFormClasse(f => ({ ...f, session_examen_id: e.target.value }))} />
        </div>
      </Modal>

      {/* Générer pour un étudiant */}
      <Modal open={modalEtudiant} onClose={() => setModalEtudiant(false)} title="Générer le bulletin d'un étudiant" size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModalEtudiant(false)}>Annuler</Button>
            <Button loading={genererEtudiantMut.isPending} onClick={() => genererEtudiantMut.mutate(formEtudiant)}>Générer</Button>
          </>
        }>
        <div className="space-y-4">
          <Input label="Étudiant ID" type="number" value={formEtudiant.etudiant_id}
            onChange={e => setFormEtudiant(f => ({ ...f, etudiant_id: e.target.value }))} />
          <Input label="Session d'examen ID" type="number" value={formEtudiant.session_examen_id}
            onChange={e => setFormEtudiant(f => ({ ...f, session_examen_id: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}
