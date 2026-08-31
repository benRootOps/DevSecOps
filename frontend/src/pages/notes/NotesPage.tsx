// src/pages/notes/NotesPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { BookOpen, ClipboardList, PlusCircle, Lock, Calculator, Trophy, Search, Trash2, Pencil } from "lucide-react";
import { Card, CardHeader, CardBody, Modal, EmptyState, Input, Select, StatCard } from "../../components/ui/components";
import { Button, Badge, Skeleton, Avatar } from "../../components/ui/atoms";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";
import { cn } from "../../utils/cn";

const TABS = ["Sessions d'examen", "Saisie des notes", "Résultats & moyennes"];

// ⚠️ Champs déduits du schéma (sessions_examen / examens / notes) — à ajuster
// selon les FormRequest exacts de NoteController si différents.

export default function NotesPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState(0);

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <BookOpen size={20} className="text-[#A78BFA]" /> Notes
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">Sessions d'examen, saisie et résultats</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl w-fit">
        {TABS.map((t, i) => (
          <button key={i} onClick={() => setTab(i)}
            className={cn("px-4 py-2 text-xs font-medium rounded-lg transition-all",
              tab === i ? "bg-[rgba(167,139,250,0.14)] text-[#F0F2FF]" : "text-[#5C6785] hover:text-[#8A97B5]")}>
            {t}
          </button>
        ))}
      </div>

      {tab === 0 && <TabSessions qc={qc} />}
      {tab === 1 && <TabSaisie />}
      {tab === 2 && <TabResultats />}
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 0 — Sessions d'examen
   ══════════════════════════════════════════════════════════════ */
function TabSessions({ qc }: { qc: ReturnType<typeof useQueryClient> }) {
  const [modalSession, setModalSession] = useState(false);
  const [formSession, setFormSession] = useState({ nom: "", semestre_id: "1", date_debut: "", date_fin: "" });
  const [expanded, setExpanded] = useState<number | null>(null);

  const { data: sessions, isLoading } = useQuery({
    queryKey: ["sessions-examen"],
    queryFn: () => client.get("/sessions-examen").then(r => r.data.donnees),
  });

  const createMut = useMutation({
    mutationFn: (d: typeof formSession) => client.post("/sessions-examen", d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["sessions-examen"] });
      setModalSession(false);
      setFormSession({ nom: "", semestre_id: "1", date_debut: "", date_fin: "" });
      toast.success("Session d'examen créée.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const cloturerMut = useMutation({
    mutationFn: (id: number) => client.patch(`/sessions-examen/${id}/cloturer`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["sessions-examen"] }); toast.success("Session clôturée."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <PermissionGate permission="examens.creer">
          <Button icon={<PlusCircle size={16} />} onClick={() => setModalSession(true)}>
            Nouvelle session
          </Button>
        </PermissionGate>
      </div>

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Session", "Semestre", "Période", "Statut", ""].map(h => (
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
                : sessions?.length === 0
                ? <tr><td colSpan={5}><EmptyState icon={<ClipboardList size={24} />} title="Aucune session" description="Créez la première session d'examen." /></td></tr>
                : sessions?.map((s: any) => (
                    <SessionRow key={s.id} s={s} expanded={expanded === s.id}
                      onToggle={() => setExpanded(expanded === s.id ? null : s.id)}
                      onCloturer={() => cloturerMut.mutate(s.id)}
                      cloturerLoading={cloturerMut.isPending} />
                  ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal open={modalSession} onClose={() => setModalSession(false)} title="Nouvelle session d'examen" size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModalSession(false)}>Annuler</Button>
            <Button loading={createMut.isPending} onClick={() => createMut.mutate(formSession)}>Créer</Button>
          </>
        }>
        <div className="space-y-4">
          <Input label="Nom" placeholder="Ex: Session normale S1" value={formSession.nom}
            onChange={e => setFormSession(f => ({ ...f, nom: e.target.value }))} />
          <Input label="Semestre ID" type="number" value={formSession.semestre_id}
            onChange={e => setFormSession(f => ({ ...f, semestre_id: e.target.value }))} />
          <div className="grid grid-cols-2 gap-3">
            <Input label="Début" type="date" value={formSession.date_debut}
              onChange={e => setFormSession(f => ({ ...f, date_debut: e.target.value }))} />
            <Input label="Fin" type="date" value={formSession.date_fin}
              onChange={e => setFormSession(f => ({ ...f, date_fin: e.target.value }))} />
          </div>
        </div>
      </Modal>
    </div>
  );
}

function SessionRow({ s, expanded, onToggle, onCloturer, cloturerLoading }: any) {
  const cloturee = s.statut === "cloturee" || s.cloturee === true;
  return (
    <>
      <tr className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors cursor-pointer" onClick={onToggle}>
        <td className="px-6 py-3 text-sm text-[#F0F2FF] font-medium">{s.nom ?? `Session #${s.id}`}</td>
        <td className="px-6 py-3 text-xs text-[#5C6785]">{s.semestre?.nom ?? s.semestre_id}</td>
        <td className="px-6 py-3 text-xs text-[#5C6785]">{s.date_debut} → {s.date_fin}</td>
        <td className="px-6 py-3">
          <Badge color={cloturee ? "#EF4444" : "#10B981"}>{cloturee ? "Clôturée" : "Ouverte"}</Badge>
        </td>
        <td className="px-6 py-3 text-right" onClick={e => e.stopPropagation()}>
          <PermissionGate permission="examens.cloturer">
            {!cloturee && (
              <Button variant="outline" size="sm" icon={<Lock size={13} />} loading={cloturerLoading} onClick={onCloturer}>
                Clôturer
              </Button>
            )}
          </PermissionGate>
        </td>
      </tr>
      {expanded && (
        <tr className="border-b border-[rgba(255,255,255,0.03)] bg-[rgba(255,255,255,0.015)]">
          <td colSpan={5} className="px-6 py-4">
            <ExamensList sessionId={s.id} />
          </td>
        </tr>
      )}
    </>
  );
}

function ExamensList({ sessionId }: { sessionId: number }) {
  const qc = useQueryClient();
  const [modalExamen, setModalExamen] = useState(false);
  const [editing, setEditing] = useState<any>(null);
  const [form, setForm] = useState({ matiere_id: "", type_evaluation: "CC", coefficient: "1", date_examen: "" });

  const { data: examens, isLoading } = useQuery({
    queryKey: ["examens", sessionId],
    queryFn: () => client.get(`/sessions-examen/${sessionId}/examens`).then(r => r.data.donnees),
  });

  const saveMut = useMutation({
    mutationFn: (d: typeof form) =>
      editing ? client.put(`/examens/${editing.id}`, d) : client.post(`/sessions-examen/${sessionId}/examens`, d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["examens", sessionId] });
      setModalExamen(false); setEditing(null);
      setForm({ matiere_id: "", type_evaluation: "CC", coefficient: "1", date_examen: "" });
      toast.success(editing ? "Examen modifié." : "Examen ajouté.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => client.delete(`/examens/${id}`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["examens", sessionId] }); toast.success("Examen supprimé."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-[#8A97B5] uppercase tracking-wide">Examens de la session</span>
        <PermissionGate permission="examens.creer">
          <Button size="sm" variant="outline" icon={<PlusCircle size={13} />}
            onClick={() => { setEditing(null); setModalExamen(true); }}>
            Ajouter un examen
          </Button>
        </PermissionGate>
      </div>

      {isLoading ? (
        <Skeleton className="h-16 rounded-lg" />
      ) : examens?.length === 0 ? (
        <p className="text-xs text-[#5C6785] py-3">Aucun examen pour cette session.</p>
      ) : (
        <div className="space-y-1.5">
          {examens?.map((ex: any) => (
            <div key={ex.id} className="flex items-center justify-between px-4 py-2.5 rounded-lg bg-[#0A0C14] border border-[rgba(255,255,255,0.04)]">
              <div className="flex items-center gap-3">
                <span className="text-sm text-[#F0F2FF]">{ex.matiere?.nom ?? `Matière #${ex.matiere_id}`}</span>
                <Badge color="#A78BFA">{ex.type_evaluation}</Badge>
                <span className="text-xs text-[#5C6785] font-mono">coef {ex.coefficient}</span>
              </div>
              <PermissionGate permission="examens.modifier">
                <div className="flex gap-1.5">
                  <button onClick={() => { setEditing(ex); setForm({ matiere_id: String(ex.matiere_id ?? ""), type_evaluation: ex.type_evaluation ?? "CC", coefficient: String(ex.coefficient ?? "1"), date_examen: ex.date_examen ?? "" }); setModalExamen(true); }}
                    className="p-1.5 rounded-lg text-[#5C6785] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.06)]">
                    <Pencil size={13} />
                  </button>
                  <button onClick={() => deleteMut.mutate(ex.id)}
                    className="p-1.5 rounded-lg text-[#5C6785] hover:text-[#EF4444] hover:bg-[rgba(239,68,68,0.1)]">
                    <Trash2 size={13} />
                  </button>
                </div>
              </PermissionGate>
            </div>
          ))}
        </div>
      )}

      <Modal open={modalExamen} onClose={() => setModalExamen(false)} title={editing ? "Modifier l'examen" : "Nouvel examen"} size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModalExamen(false)}>Annuler</Button>
            <Button loading={saveMut.isPending} onClick={() => saveMut.mutate(form)}>{editing ? "Enregistrer" : "Ajouter"}</Button>
          </>
        }>
        <div className="space-y-4">
          <Input label="Matière ID" type="number" value={form.matiere_id} onChange={e => setForm(f => ({ ...f, matiere_id: e.target.value }))} />
          <Select label="Type d'évaluation" value={form.type_evaluation}
            onChange={e => setForm(f => ({ ...f, type_evaluation: e.target.value }))}
            options={["CC", "SN", "TP", "Examen final"].map(v => ({ value: v, label: v }))} />
          <Input label="Coefficient" type="number" value={form.coefficient} onChange={e => setForm(f => ({ ...f, coefficient: e.target.value }))} />
          <Input label="Date" type="date" value={form.date_examen} onChange={e => setForm(f => ({ ...f, date_examen: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 1 — Saisie des notes
   ══════════════════════════════════════════════════════════════ */
function TabSaisie() {
  const qc = useQueryClient();
  const [matiereId, setMatiereId] = useState("1");
  const [sessionId, setSessionId] = useState("1");
  const [classeId, setClasseId] = useState("1");
  const [notesLocal, setNotesLocal] = useState<Record<number, string>>({});

  const { data: notes, isLoading } = useQuery({
    queryKey: ["notes-saisie", matiereId, sessionId, classeId],
    queryFn: () => client.get(`/notes/matiere/${matiereId}/session/${sessionId}/classe/${classeId}`).then(r => r.data.donnees),
  });

  const saveMassMut = useMutation({
    mutationFn: (payload: { notes: { etudiant_id: number; note: number }[] }) =>
      client.post("/notes/masse", {
        matiere_id: Number(matiereId), session_examen_id: Number(sessionId), classe_id: Number(classeId),
        notes: payload.notes,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["notes-saisie", matiereId, sessionId, classeId] });
      toast.success("Notes enregistrées.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const validerMut = useMutation({
    mutationFn: () => client.patch("/notes/valider", { matiere_id: Number(matiereId), session_examen_id: Number(sessionId), classe_id: Number(classeId) }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["notes-saisie"] }); toast.success("Notes validées."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const rows: any[] = notes ?? [];
  const dirtyCount = Object.keys(notesLocal).length;

  function handleChange(etuId: number, value: string) {
    setNotesLocal(prev => ({ ...prev, [etuId]: value }));
  }

  function handleSaveAll() {
    const payload = Object.entries(notesLocal)
      .filter(([, v]) => v !== "")
      .map(([etuId, v]) => ({ etudiant_id: Number(etuId), note: Number(v) }));
    if (payload.length === 0) { toast.info("Aucune note modifiée."); return; }
    saveMassMut.mutate({ notes: payload });
    setNotesLocal({});
  }

  return (
    <div className="space-y-4">
      <div className="flex items-end gap-3 flex-wrap">
        <Input label="Matière ID" value={matiereId} onChange={e => setMatiereId(e.target.value)} type="number" className="max-w-[140px]" />
        <Input label="Session ID" value={sessionId} onChange={e => setSessionId(e.target.value)} type="number" className="max-w-[140px]" />
        <Input label="Classe ID" value={classeId} onChange={e => setClasseId(e.target.value)} type="number" className="max-w-[140px]" />
        <PermissionGate permission="notes.saisir">
          <Button variant="outline" icon={<Search size={14} />} loading={dirtyCount > 0 && saveMassMut.isPending} disabled={dirtyCount === 0} onClick={handleSaveAll}>
            Enregistrer {dirtyCount > 0 ? `(${dirtyCount})` : ""}
          </Button>
        </PermissionGate>
        <PermissionGate permission="notes.valider">
          <Button variant="success" loading={validerMut.isPending} onClick={() => validerMut.mutate()}>
            Valider les notes
          </Button>
        </PermissionGate>
      </div>

      <Card>
        <CardHeader>
          <span className="text-sm font-semibold text-[#F0F2FF]">{rows.length} étudiant(s)</span>
        </CardHeader>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Étudiant", "Matricule", "Note actuelle", "Nouvelle note", "Statut"].map(h => (
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
                ? <tr><td colSpan={5}><EmptyState icon={<BookOpen size={24} />} title="Aucun étudiant" description="Vérifiez les identifiants matière / session / classe." /></td></tr>
                : rows.map((n: any) => {
                    const etuId = n.etudiant_id ?? n.etudiant?.id;
                    return (
                      <tr key={etuId} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                        <td className="px-6 py-3">
                          <div className="flex items-center gap-3">
                            <Avatar nom={n.etudiant?.nom} prenom={n.etudiant?.prenom} size="sm" />
                            <span className="text-sm text-[#F0F2FF]">{n.etudiant?.prenom} {n.etudiant?.nom}</span>
                          </div>
                        </td>
                        <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{n.etudiant?.matricule}</td>
                        <td className="px-6 py-3 text-sm font-mono text-[#8A97B5]">{n.note ?? "—"} / 20</td>
                        <td className="px-6 py-3">
                          <PermissionGate permission="notes.saisir" fallback={<span className="text-xs text-[#5C6785]">—</span>}>
                            <input
                              type="number" min={0} max={20} step={0.25}
                              placeholder="/20"
                              value={notesLocal[etuId] ?? ""}
                              onChange={e => handleChange(etuId, e.target.value)}
                              className="w-20 rounded-lg bg-[#0A0C14] border border-[rgba(255,255,255,0.08)] focus:border-[rgba(167,139,250,0.6)] text-[#F0F2FF] text-sm px-2.5 py-1.5 outline-none font-mono"
                            />
                          </PermissionGate>
                        </td>
                        <td className="px-6 py-3">
                          <Badge color={n.valide ? "#10B981" : "#F59E0B"}>{n.valide ? "Validée" : "Brouillon"}</Badge>
                        </td>
                      </tr>
                    );
                  })}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 2 — Résultats & moyennes
   ══════════════════════════════════════════════════════════════ */
function TabResultats() {
  const qc = useQueryClient();
  const [sessionId, setSessionId] = useState("1");
  const [classeId, setClasseId] = useState("1");
  const [releveEtuId, setReleveEtuId] = useState("");
  const [releveOpen, setReleveOpen] = useState(false);

  const { data: resultats, isLoading } = useQuery({
    queryKey: ["resultats", sessionId, classeId],
    queryFn: () => client.get(`/sessions-examen/${sessionId}/resultats/${classeId}`).then(r => r.data.donnees),
  });

  const calculerMut = useMutation({
    mutationFn: () => client.post(`/sessions-examen/${sessionId}/calculer-moyennes`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["resultats", sessionId, classeId] }); toast.success("Moyennes calculées."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const { data: releve, isFetching: releveLoading } = useQuery({
    queryKey: ["releve", releveEtuId, sessionId],
    queryFn: () => client.get(`/etudiants/${releveEtuId}/releve/${sessionId}`).then(r => r.data.donnees),
    enabled: releveOpen && !!releveEtuId,
  });

  const rows: any[] = resultats ?? [];
  const admisCount = rows.filter((r: any) => r.decision === "Admis" || r.admis === true).length;
  const moyenneClasse = rows.length ? (rows.reduce((s: number, r: any) => s + Number(r.moyenne ?? 0), 0) / rows.length).toFixed(2) : "—";

  return (
    <div className="space-y-4">
      <div className="flex items-end gap-3 flex-wrap">
        <Input label="Session ID" value={sessionId} onChange={e => setSessionId(e.target.value)} type="number" className="max-w-[140px]" />
        <Input label="Classe ID" value={classeId} onChange={e => setClasseId(e.target.value)} type="number" className="max-w-[140px]" />
        <PermissionGate permission="notes.valider">
          <Button variant="outline" icon={<Calculator size={14} />} loading={calculerMut.isPending} onClick={() => calculerMut.mutate()}>
            Calculer les moyennes
          </Button>
        </PermissionGate>

        <div className="flex items-end gap-2 ml-auto">
          <Input label="Relevé — Étudiant ID" value={releveEtuId} onChange={e => setReleveEtuId(e.target.value)} type="number" className="max-w-[160px]" />
          <Button variant="ghost" icon={<Search size={14} />} onClick={() => setReleveOpen(true)} disabled={!releveEtuId}>
            Voir le relevé
          </Button>
        </div>
      </div>

      {rows.length > 0 && (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          <StatCard label="Étudiants" value={rows.length} icon={<BookOpen size={18} />} color="#A78BFA" />
          <StatCard label="Moyenne de classe" value={moyenneClasse} icon={<Calculator size={18} />} color="#06B6D4" />
          <StatCard label="Admis" value={admisCount} icon={<Trophy size={18} />} color="#10B981" />
        </div>
      )}

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Rang", "Étudiant", "Matricule", "Moyenne", "Décision"].map(h => (
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
                ? <tr><td colSpan={5}><EmptyState icon={<Trophy size={24} />} title="Aucun résultat" description="Calculez les moyennes pour cette session et classe." /></td></tr>
                : rows.map((r: any, i: number) => {
                    const moy = Number(r.moyenne ?? 0);
                    const admis = r.decision === "Admis" || r.admis === true;
                    return (
                      <tr key={r.etudiant_id ?? i} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                        <td className="px-6 py-3 text-sm font-mono text-[#5C6785]">{r.rang ?? i + 1}</td>
                        <td className="px-6 py-3">
                          <div className="flex items-center gap-3">
                            <Avatar nom={r.etudiant?.nom} prenom={r.etudiant?.prenom} size="sm" />
                            <span className="text-sm text-[#F0F2FF]">{r.etudiant?.prenom} {r.etudiant?.nom}</span>
                          </div>
                        </td>
                        <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{r.etudiant?.matricule}</td>
                        <td className="px-6 py-3 text-sm font-mono font-bold" style={{ color: moy >= 10 ? "#10B981" : "#EF4444" }}>{moy.toFixed(2)} / 20</td>
                        <td className="px-6 py-3">
                          <Badge color={admis ? "#10B981" : "#EF4444"}>{r.decision ?? (admis ? "Admis" : "Ajourné")}</Badge>
                        </td>
                      </tr>
                    );
                  })}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal open={releveOpen} onClose={() => setReleveOpen(false)} title={`Relevé — Étudiant #${releveEtuId}`} size="lg">
        {releveLoading ? (
          <div className="space-y-2">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-10 rounded-lg" />)}</div>
        ) : !releve ? (
          <EmptyState title="Aucune donnée" description="Aucun relevé trouvé pour cet étudiant et cette session." />
        ) : (
          <div className="space-y-2">
            {(releve.matieres ?? releve ?? []).map?.((m: any, i: number) => (
              <div key={i} className="flex items-center justify-between px-4 py-2.5 rounded-lg bg-[#0A0C14] border border-[rgba(255,255,255,0.04)]">
                <span className="text-sm text-[#F0F2FF]">{m.matiere?.nom ?? m.nom}</span>
                <span className="text-sm font-mono font-bold" style={{ color: Number(m.moyenne ?? m.note) >= 10 ? "#10B981" : "#EF4444" }}>
                  {m.moyenne ?? m.note} / 20
                </span>
              </div>
            ))}
          </div>
        )}
      </Modal>
    </div>
  );
}
