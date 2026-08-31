// src/pages/utilisateurs/EnseignantsPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { GraduationCap, Search, PlusCircle, Trash2, ChevronDown, ChevronUp } from "lucide-react";
import { Card, Modal, EmptyState, Input } from "../../components/ui/components";
import { Button, Badge, Skeleton, Avatar } from "../../components/ui/atoms";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";

export default function EnseignantsPage() {
  const [search, setSearch] = useState("");
  const [expanded, setExpanded] = useState<number | null>(null);

  const { data: enseignants, isLoading } = useQuery({
    queryKey: ["enseignants", search],
    queryFn: () => client.get("/enseignants", { params: { recherche: search || undefined } }).then(r => r.data.donnees),
  });

  const rows: any[] = enseignants?.data ?? enseignants ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <GraduationCap size={20} className="text-[#A78BFA]" /> Enseignants
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">{rows.length} enseignant(s)</p>
        </div>
      </div>

      <div className="relative max-w-xs">
        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#5C6785]" />
        <input
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Rechercher un enseignant…"
          className="w-full pl-9 pr-4 py-2 text-sm bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-lg text-[#F0F2FF] placeholder:text-[#5C6785] outline-none focus:border-[rgba(124,106,247,0.4)]"
        />
      </div>

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Enseignant", "Spécialité", "Email", "Diplômes", ""].map(h => (
                  <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 6 }).map((_, i) => (
                    <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                      {[1, 2, 3, 4, 5].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                    </tr>
                  ))
                : rows.length === 0
                ? <tr><td colSpan={5}><EmptyState icon={<GraduationCap size={24} />} title="Aucun enseignant" description="Créez un compte enseignant depuis la page Utilisateurs." /></td></tr>
                : rows.map((e: any) => (
                    <EnseignantRow key={e.id} e={e} expanded={expanded === e.id}
                      onToggle={() => setExpanded(expanded === e.id ? null : e.id)} />
                  ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

function EnseignantRow({ e, expanded, onToggle }: any) {
  return (
    <>
      <tr className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors cursor-pointer" onClick={onToggle}>
        <td className="px-6 py-3">
          <div className="flex items-center gap-3">
            <Avatar src={e.utilisateur?.photo_url} nom={e.utilisateur?.nom ?? e.nom} prenom={e.utilisateur?.prenom ?? e.prenom} size="sm" />
            <span className="text-sm text-[#F0F2FF]">{e.utilisateur?.prenom ?? e.prenom} {e.utilisateur?.nom ?? e.nom}</span>
          </div>
        </td>
        <td className="px-6 py-3"><Badge color="#A78BFA">{e.specialite ?? "—"}</Badge></td>
        <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{e.utilisateur?.email ?? e.email}</td>
        <td className="px-6 py-3 text-xs text-[#5C6785]">{e.diplomes?.length ?? 0}</td>
        <td className="px-6 py-3 text-right text-[#5C6785]">{expanded ? <ChevronUp size={15} /> : <ChevronDown size={15} />}</td>
      </tr>
      {expanded && (
        <tr className="border-b border-[rgba(255,255,255,0.03)] bg-[rgba(255,255,255,0.015)]">
          <td colSpan={5} className="px-6 py-4">
            <DiplomesList enseignantId={e.id} diplomes={e.diplomes ?? []} />
          </td>
        </tr>
      )}
    </>
  );
}

function DiplomesList({ enseignantId, diplomes }: { enseignantId: number; diplomes: any[] }) {
  const qc = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ intitule: "", etablissement: "", annee_obtention: "" });

  const addMut = useMutation({
    mutationFn: (d: typeof form) => client.post(`/enseignants/${enseignantId}/diplomes`, { ...d, annee_obtention: Number(d.annee_obtention) }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["enseignants"] });
      setModalOpen(false);
      setForm({ intitule: "", etablissement: "", annee_obtention: "" });
      toast.success("Diplôme ajouté.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => client.delete(`/diplomes/${id}`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["enseignants"] }); toast.success("Diplôme supprimé."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-[#8A97B5] uppercase tracking-wide">Diplômes</span>
        <PermissionGate permission="enseignants.modifier">
          <Button size="sm" variant="outline" icon={<PlusCircle size={13} />} onClick={() => setModalOpen(true)}>
            Ajouter un diplôme
          </Button>
        </PermissionGate>
      </div>

      {diplomes.length === 0 ? (
        <p className="text-xs text-[#5C6785] py-2">Aucun diplôme enregistré.</p>
      ) : (
        <div className="space-y-1.5">
          {diplomes.map((d: any) => (
            <div key={d.id} className="flex items-center justify-between px-4 py-2.5 rounded-lg bg-[#0A0C14] border border-[rgba(255,255,255,0.04)]">
              <div>
                <span className="text-sm text-[#F0F2FF]">{d.intitule}</span>
                <span className="text-xs text-[#5C6785] ml-2">{d.etablissement} · {d.annee_obtention}</span>
              </div>
              <PermissionGate permission="enseignants.modifier">
                <button onClick={() => deleteMut.mutate(d.id)} className="p-1.5 rounded-lg text-[#5C6785] hover:text-[#EF4444] hover:bg-[rgba(239,68,68,0.1)]">
                  <Trash2 size={13} />
                </button>
              </PermissionGate>
            </div>
          ))}
        </div>
      )}

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Ajouter un diplôme" size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalOpen(false)}>Annuler</Button><Button loading={addMut.isPending} onClick={() => addMut.mutate(form)}>Ajouter</Button></>}>
        <div className="space-y-4">
          <Input label="Intitulé" placeholder="Ex: Master en Informatique" value={form.intitule} onChange={e => setForm(f => ({ ...f, intitule: e.target.value }))} />
          <Input label="Établissement" value={form.etablissement} onChange={e => setForm(f => ({ ...f, etablissement: e.target.value }))} />
          <Input label="Année d'obtention" type="number" value={form.annee_obtention} onChange={e => setForm(f => ({ ...f, annee_obtention: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}
