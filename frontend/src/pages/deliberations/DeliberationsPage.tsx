// src/pages/deliberations/DeliberationsPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Award, PlusCircle, Lock, Sparkles, UserCheck, ChevronRight } from "lucide-react";
import { Card, CardHeader, CardBody, Modal, EmptyState, Input, Select } from "../../components/ui/components";
import { Button, Badge, Skeleton, Avatar } from "../../components/ui/atoms";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";
import { cn } from "../../utils/cn";

const TABS = ["Délibérations", "Résultats"];

// ⚠️ Champs déduits du schéma (deliberations / deliberation_resultats) — à
// ajuster selon les FormRequest exacts de DeliberationController si différents.

const DECISIONS = ["Admis", "Ajourné", "Redoublant", "Exclu"];
const DECISION_COLOR: Record<string, string> = {
  "Admis": "#10B981", "Ajourné": "#F59E0B", "Redoublant": "#EF4444", "Exclu": "#5C6785",
};

export default function DeliberationsPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState(0);
  const [selectedId, setSelectedId] = useState<number | null>(null);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <Award size={20} className="text-[#EC4899]" /> Délibérations
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">Jurys, décisions et clôtures de session</p>
        </div>
      </div>

      <div className="flex gap-1 p-1 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl w-fit">
        {TABS.map((t, i) => (
          <button key={i} onClick={() => setTab(i)}
            className={cn("px-4 py-2 text-xs font-medium rounded-lg transition-all",
              tab === i ? "bg-[rgba(236,72,153,0.14)] text-[#F0F2FF]" : "text-[#5C6785] hover:text-[#8A97B5]")}>
            {t}
          </button>
        ))}
      </div>

      {tab === 0 && <TabListe qc={qc} onVoirResultats={(id) => { setSelectedId(id); setTab(1); }} />}
      {tab === 1 && <TabResultats deliberationId={selectedId} onChangeId={setSelectedId} />}
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 0 — Liste des délibérations
   ══════════════════════════════════════════════════════════════ */
function TabListe({ qc, onVoirResultats }: { qc: ReturnType<typeof useQueryClient>; onVoirResultats: (id: number) => void }) {
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ classe_id: "1", semestre_id: "1", session_examen_id: "1", date_deliberation: "" });

  const { data: deliberations, isLoading } = useQuery({
    queryKey: ["deliberations"],
    queryFn: () => client.get("/deliberations").then(r => r.data.donnees),
  });

  const createMut = useMutation({
    mutationFn: (d: typeof form) => client.post("/deliberations", d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["deliberations"] });
      setModalOpen(false);
      setForm({ classe_id: "1", semestre_id: "1", session_examen_id: "1", date_deliberation: "" });
      toast.success("Délibération créée.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const cloturerMut = useMutation({
    mutationFn: (id: number) => client.patch(`/deliberations/${id}/cloturer`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["deliberations"] }); toast.success("Délibération clôturée."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const rows: any[] = deliberations ?? [];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <PermissionGate permission="deliberations.creer">
          <Button icon={<PlusCircle size={16} />} onClick={() => setModalOpen(true)}>
            Nouvelle délibération
          </Button>
        </PermissionGate>
      </div>

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Classe", "Semestre", "Date", "Statut", ""].map(h => (
                  <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 4 }).map((_, i) => (
                    <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                      {[1, 2, 3, 4, 5].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                    </tr>
                  ))
                : rows.length === 0
                ? <tr><td colSpan={5}><EmptyState icon={<Award size={24} />} title="Aucune délibération" description="Créez la première délibération de jury." /></td></tr>
                : rows.map((d: any) => {
                    const cloturee = d.statut === "cloturee" || d.cloturee === true;
                    return (
                      <tr key={d.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                        <td className="px-6 py-3 text-sm text-[#F0F2FF] font-medium">{d.classe?.nom ?? `Classe #${d.classe_id}`}</td>
                        <td className="px-6 py-3 text-xs text-[#5C6785]">{d.semestre?.nom ?? d.semestre_id}</td>
                        <td className="px-6 py-3 text-xs text-[#5C6785]">{d.date_deliberation ?? "—"}</td>
                        <td className="px-6 py-3">
                          <Badge color={cloturee ? "#EF4444" : "#10B981"}>{cloturee ? "Clôturée" : "En cours"}</Badge>
                        </td>
                        <td className="px-6 py-3 text-right">
                          <div className="flex justify-end gap-2">
                            <Button variant="ghost" size="sm" icon={<ChevronRight size={13} />} onClick={() => onVoirResultats(d.id)}>
                              Résultats
                            </Button>
                            <PermissionGate permission="deliberations.cloturer">
                              {!cloturee && (
                                <Button variant="outline" size="sm" icon={<Lock size={13} />}
                                  loading={cloturerMut.isPending} onClick={() => cloturerMut.mutate(d.id)}>
                                  Clôturer
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

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nouvelle délibération" size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModalOpen(false)}>Annuler</Button>
            <Button loading={createMut.isPending} onClick={() => createMut.mutate(form)}>Créer</Button>
          </>
        }>
        <div className="space-y-4">
          <Input label="Classe ID" type="number" value={form.classe_id} onChange={e => setForm(f => ({ ...f, classe_id: e.target.value }))} />
          <Input label="Semestre ID" type="number" value={form.semestre_id} onChange={e => setForm(f => ({ ...f, semestre_id: e.target.value }))} />
          <Input label="Session d'examen ID" type="number" value={form.session_examen_id} onChange={e => setForm(f => ({ ...f, session_examen_id: e.target.value }))} />
          <Input label="Date de délibération" type="date" value={form.date_deliberation} onChange={e => setForm(f => ({ ...f, date_deliberation: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 1 — Résultats d'une délibération
   ══════════════════════════════════════════════════════════════ */
function TabResultats({ deliberationId, onChangeId }: { deliberationId: number | null; onChangeId: (id: number | null) => void }) {
  const qc = useQueryClient();
  const [idInput, setIdInput] = useState(deliberationId ? String(deliberationId) : "");
  const [modalManuel, setModalManuel] = useState(false);
  const [formManuel, setFormManuel] = useState({ etudiant_id: "", decision: "Admis", observation: "" });

  const activeId = deliberationId;

  const { data: resultats, isLoading } = useQuery({
    queryKey: ["deliberation-resultats", activeId],
    queryFn: () => client.get(`/deliberations/${activeId}/resultats`).then(r => r.data.donnees),
    enabled: !!activeId,
  });

  const autoMut = useMutation({
    mutationFn: () => client.post(`/deliberations/${activeId}/auto-decisions`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["deliberation-resultats", activeId] }); toast.success("Décisions automatiques générées."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const manuelMut = useMutation({
    mutationFn: (d: typeof formManuel) => client.post(`/deliberations/${activeId}/resultats`, {
      etudiant_id: Number(d.etudiant_id), decision: d.decision, observation: d.observation,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["deliberation-resultats", activeId] });
      setModalManuel(false);
      setFormManuel({ etudiant_id: "", decision: "Admis", observation: "" });
      toast.success("Résultat ajouté.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const rows: any[] = resultats ?? [];

  return (
    <div className="space-y-4">
      <div className="flex items-end gap-3 flex-wrap">
        <Input label="Délibération ID" type="number" value={idInput}
          onChange={e => setIdInput(e.target.value)}
          className="max-w-[160px]" />
        <Button variant="outline" onClick={() => onChangeId(idInput ? Number(idInput) : null)} disabled={!idInput}>
          Charger
        </Button>
        <div className="ml-auto flex gap-2">
          <PermissionGate permission="deliberations.creer">
            <Button variant="outline" icon={<Sparkles size={14} />} loading={autoMut.isPending}
              disabled={!activeId} onClick={() => autoMut.mutate()}>
              Décisions automatiques
            </Button>
            <Button icon={<UserCheck size={14} />} disabled={!activeId} onClick={() => setModalManuel(true)}>
              Ajouter un résultat
            </Button>
          </PermissionGate>
        </div>
      </div>

      {!activeId ? (
        <Card><CardBody><EmptyState icon={<Award size={24} />} title="Aucune délibération sélectionnée" description="Saisissez un ID ou passez par l'onglet Délibérations." /></CardBody></Card>
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-[rgba(255,255,255,0.05)]">
                  {["Étudiant", "Matricule", "Moyenne", "Décision", "Observation"].map(h => (
                    <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {isLoading
                  ? Array.from({ length: 5 }).map((_, i) => (
                      <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                        {[1, 2, 3, 4, 5].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                      </tr>
                    ))
                  : rows.length === 0
                  ? <tr><td colSpan={5}><EmptyState icon={<Award size={24} />} title="Aucun résultat" description="Générez les décisions automatiques ou ajoutez un résultat manuel." /></td></tr>
                  : rows.map((r: any, i: number) => (
                      <tr key={r.etudiant_id ?? i} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                        <td className="px-6 py-3">
                          <div className="flex items-center gap-3">
                            <Avatar nom={r.etudiant?.nom} prenom={r.etudiant?.prenom} size="sm" />
                            <span className="text-sm text-[#F0F2FF]">{r.etudiant?.prenom} {r.etudiant?.nom}</span>
                          </div>
                        </td>
                        <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{r.etudiant?.matricule}</td>
                        <td className="px-6 py-3 text-sm font-mono text-[#8A97B5]">{r.moyenne ?? "—"} / 20</td>
                        <td className="px-6 py-3">
                          <Badge color={DECISION_COLOR[r.decision] ?? "#7C6AF7"}>{r.decision ?? "—"}</Badge>
                        </td>
                        <td className="px-6 py-3 text-xs text-[#5C6785]">{r.observation ?? "—"}</td>
                      </tr>
                    ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      <Modal open={modalManuel} onClose={() => setModalManuel(false)} title="Ajouter un résultat" size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModalManuel(false)}>Annuler</Button>
            <Button loading={manuelMut.isPending} onClick={() => manuelMut.mutate(formManuel)}>Enregistrer</Button>
          </>
        }>
        <div className="space-y-4">
          <Input label="Étudiant ID" type="number" value={formManuel.etudiant_id}
            onChange={e => setFormManuel(f => ({ ...f, etudiant_id: e.target.value }))} />
          <Select label="Décision" value={formManuel.decision}
            onChange={e => setFormManuel(f => ({ ...f, decision: e.target.value }))}
            options={DECISIONS.map(v => ({ value: v, label: v }))} />
          <Input label="Observation" value={formManuel.observation} placeholder="Optionnel"
            onChange={e => setFormManuel(f => ({ ...f, observation: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}
