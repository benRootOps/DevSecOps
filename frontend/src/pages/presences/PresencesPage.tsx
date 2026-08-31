// src/pages/presences/PresencesPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { CheckSquare, Users, TrendingUp, AlertTriangle, Search } from "lucide-react";
import { Card, CardHeader, CardBody, Modal, EmptyState, Input, Select, StatCard } from "../../components/ui/components";
import { Button, Badge, Skeleton, Avatar } from "../../components/ui/atoms";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";
import { cn } from "../../utils/cn";

const STATUTS = ["Présent", "Absent", "Retard", "Excusé"];
const STATUT_CONFIG: Record<string, { color: string; bg: string }> = {
  "Présent": { color: "#10B981", bg: "rgba(16,185,129,0.1)"  },
  "Absent":  { color: "#EF4444", bg: "rgba(239,68,68,0.1)"   },
  "Retard":  { color: "#F59E0B", bg: "rgba(245,158,11,0.1)"  },
  "Excusé":  { color: "#06B6D4", bg: "rgba(6,182,212,0.1)"   },
};

const TABS = ["Feuille de présence", "Statistiques étudiant", "Statistiques classe"];

export default function PresencesPage() {
  const qc = useQueryClient();
  const [tab, setTab]         = useState(0);
  const [seanceId, setSeanceId] = useState("1");
  const [etuId,    setEtuId]    = useState("6");
  const [classeId, setClasseId] = useState("1");
  const [semestreId, setSemId]  = useState("1");
  const [seuil, setSeuil]       = useState("75");
  const [modalEns, setModalEns] = useState(false);
  const [formEns, setFormEns]   = useState({ enseignant_id: "5", statut: "Présent", observations: "" });

  // Feuille de présence
  const { data: feuille, isLoading: feuilleLoading } = useQuery({
    queryKey: ["presences-feuille", seanceId],
    queryFn: () => client.get(`/seances/${seanceId}/presences`).then(r => r.data.donnees),
    enabled: tab === 0 && !!seanceId,
  });

  // Stats étudiant
  const { data: statsEtu } = useQuery({
    queryKey: ["stats-presences-etu", etuId, classeId, semestreId],
    queryFn: () => client.get(`/etudiants/${etuId}/statistiques-presences`, {
      params: { classe_id: classeId, semestre_id: semestreId },
    }).then(r => r.data.donnees),
    enabled: tab === 1,
  });

  // Stats classe
  const { data: statsCls } = useQuery({
    queryKey: ["stats-presences-cls", classeId, semestreId, seuil],
    queryFn: () => client.get(`/classes/${classeId}/statistiques-presences`, {
      params: { semestre_id: semestreId, seuil_alerte: seuil },
    }).then(r => r.data.donnees),
    enabled: tab === 2,
  });

  // Initialiser feuille
  const initMut = useMutation({
    mutationFn: () => client.post(`/seances/${seanceId}/presences/initialiser`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["presences-feuille"] }); toast.success("Feuille initialisée."); },
    onError:   (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  // Modifier présence individuelle
  const updateMut = useMutation({
    mutationFn: ({ etuId, statut, motif }: { etuId: number; statut: string; motif?: string }) =>
      client.patch(`/seances/${seanceId}/presences/${etuId}`, { statut, motif }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["presences-feuille"] }); toast.success("Présence mise à jour."); },
    onError:   (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  // Présence enseignant
  const enseignantMut = useMutation({
    mutationFn: (d: typeof formEns) => client.post(`/seances/${seanceId}/presence-enseignant`, {
      enseignant_id: Number(d.enseignant_id), statut: d.statut, observations: d.observations,
    }),
    onSuccess: () => { setModalEns(false); toast.success("Présence enseignant enregistrée."); },
    onError:   (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <CheckSquare size={20} className="text-[#10B981]" /> Présences
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">Gestion et suivi des présences</p>
        </div>
        <PermissionGate permission="presences.saisir">
          <Button variant="outline" onClick={() => setModalEns(true)}>
            Présence enseignant
          </Button>
        </PermissionGate>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl w-fit">
        {TABS.map((t, i) => (
          <button key={i} onClick={() => setTab(i)}
            className={cn("px-4 py-2 text-xs font-medium rounded-lg transition-all",
              tab === i ? "bg-[rgba(16,185,129,0.12)] text-[#F0F2FF]" : "text-[#5C6785] hover:text-[#8A97B5]")}>
            {t}
          </button>
        ))}
      </div>

      {/* ── Tab 0 : Feuille de présence ── */}
      {tab === 0 && (
        <div className="space-y-4">
          <div className="flex items-end gap-3 flex-wrap">
            <Input label="ID Séance" value={seanceId} onChange={e => setSeanceId(e.target.value)} type="number" className="max-w-[140px]" />
            <PermissionGate permission="presences.saisir">
              <Button variant="outline" loading={initMut.isPending} onClick={() => initMut.mutate()}>
                Initialiser la feuille
              </Button>
            </PermissionGate>
          </div>

          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <span className="text-sm font-semibold text-[#F0F2FF]">
                  Séance #{seanceId} · {feuille?.length ?? 0} étudiant(s)
                </span>
                <div className="flex gap-2">
                  {Object.entries(STATUT_CONFIG).map(([s, c]) => (
                    <span key={s} className="text-[10px] px-2 py-0.5 rounded-full font-medium"
                      style={{ background: c.bg, color: c.color }}>{s}</span>
                  ))}
                </div>
              </div>
            </CardHeader>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-[rgba(255,255,255,0.05)]">
                    {["Étudiant", "Matricule", "Statut", "Motif", ""].map(h => (
                      <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {feuilleLoading
                    ? Array.from({ length: 6 }).map((_, i) => (
                        <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                          {[1,2,3,4,5].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                        </tr>
                      ))
                    : feuille?.length === 0
                    ? <tr><td colSpan={5}><EmptyState icon={<Users size={24} />} title="Feuille vide" description="Initialisez la feuille pour commencer." /></td></tr>
                    : feuille?.map((p: any) => {
                        const sc = STATUT_CONFIG[p.statut] ?? STATUT_CONFIG["Présent"];
                        return (
                          <tr key={p.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                            <td className="px-6 py-3">
                              <div className="flex items-center gap-3">
                                <Avatar nom={p.etudiant?.nom} prenom={p.etudiant?.prenom} size="sm" />
                                <span className="text-sm text-[#F0F2FF]">{p.etudiant?.prenom} {p.etudiant?.nom}</span>
                              </div>
                            </td>
                            <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{p.etudiant?.matricule}</td>
                            <td className="px-6 py-3">
                              <PermissionGate permission="presences.saisir"
                                fallback={<Badge color={sc.color}>{p.statut}</Badge>}>
                                <select
                                  value={p.statut}
                                  onChange={e => updateMut.mutate({ etuId: p.etudiant_id, statut: e.target.value })}
                                  className="text-xs px-2 py-1 rounded-lg border outline-none cursor-pointer"
                                  style={{ background: sc.bg, color: sc.color, borderColor: `${sc.color}30` }}
                                >
                                  {STATUTS.map(s => (
                                    <option key={s} value={s} className="bg-[#111420] text-[#F0F2FF]">{s}</option>
                                  ))}
                                </select>
                              </PermissionGate>
                            </td>
                            <td className="px-6 py-3 text-xs text-[#5C6785]">{p.motif ?? "—"}</td>
                            <td className="px-6 py-3 text-xs text-[#5C6785]">
                              {p.saisie_par ? `Par ${p.saisie_par?.nom}` : ""}
                            </td>
                          </tr>
                        );
                      })}
                </tbody>
              </table>
            </div>
          </Card>
        </div>
      )}

      {/* ── Tab 1 : Stats étudiant ── */}
      {tab === 1 && (
        <div className="space-y-5">
          <div className="flex items-end gap-3 flex-wrap">
            <Input label="ID Étudiant" value={etuId}    onChange={e => setEtuId(e.target.value)}    type="number" className="max-w-[140px]" />
            <Input label="Classe ID"   value={classeId}  onChange={e => setClasseId(e.target.value)} type="number" className="max-w-[140px]" />
            <Input label="Semestre ID" value={semestreId} onChange={e => setSemId(e.target.value)}  type="number" className="max-w-[140px]" />
          </div>

          {statsEtu && (
            <>
              <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                <StatCard label="Total séances" value={statsEtu.total}   icon={<CheckSquare size={18}/>} color="#7C6AF7" />
                <StatCard label="Présent"        value={statsEtu.present} icon={<CheckSquare size={18}/>} color="#10B981" />
                <StatCard label="Retard"         value={statsEtu.retard}  icon={<AlertTriangle size={18}/>} color="#F59E0B" />
                <StatCard label="Excusé"         value={statsEtu.excus}   icon={<CheckSquare size={18}/>} color="#06B6D4" />
                <StatCard label="Absent"         value={statsEtu.absent}  icon={<AlertTriangle size={18}/>} color="#EF4444" />
              </div>
              <Card>
                <CardBody>
                  <div className="flex items-center justify-between mb-3">
                    <span className="text-sm font-semibold text-[#F0F2FF]">Taux d'assiduité</span>
                    <span className="text-2xl font-bold font-mono" style={{ color: statsEtu.taux >= 75 ? "#10B981" : "#EF4444" }}>
                      {statsEtu.taux}%
                    </span>
                  </div>
                  <div className="w-full h-2 bg-[rgba(255,255,255,0.06)] rounded-full overflow-hidden">
                    <div className="h-full rounded-full transition-all duration-700"
                      style={{ width: `${statsEtu.taux}%`, background: statsEtu.taux >= 75 ? "#10B981" : "#EF4444" }} />
                  </div>
                  <div className="flex justify-between text-xs text-[#5C6785] mt-1.5">
                    <span>0%</span>
                    <span className={statsEtu.taux >= 75 ? "text-[#10B981]" : "text-[#EF4444]"}>
                      {statsEtu.taux >= 75 ? "✓ Assiduité satisfaisante" : "⚠ En dessous du seuil (75%)"}
                    </span>
                    <span>100%</span>
                  </div>
                </CardBody>
              </Card>
            </>
          )}
        </div>
      )}

      {/* ── Tab 2 : Stats classe ── */}
      {tab === 2 && (
        <div className="space-y-5">
          <div className="flex items-end gap-3 flex-wrap">
            <Input label="Classe ID"        value={classeId}   onChange={e => setClasseId(e.target.value)}  type="number" className="max-w-[140px]" />
            <Input label="Semestre ID"      value={semestreId} onChange={e => setSemId(e.target.value)}     type="number" className="max-w-[140px]" />
            <Input label="Seuil alerte (%)" value={seuil}      onChange={e => setSeuil(e.target.value)}     type="number" className="max-w-[140px]" />
          </div>

          {statsCls && (
            <>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <StatCard label="Taux moyen"         value={`${statsCls.taux_moyen}%`}                    icon={<TrendingUp size={18}/>} color="#10B981" />
                <StatCard label="Étudiants à risque"  value={Object.keys(statsCls.etudiants_a_risque).length} icon={<AlertTriangle size={18}/>} color="#EF4444" />
                <StatCard label="Total étudiants"    value={statsCls.detail?.length ?? 0}                icon={<Users size={18}/>}     color="#7C6AF7" />
              </div>

              {Object.keys(statsCls.etudiants_a_risque).length > 0 && (
                <Card>
                  <CardHeader>
                    <span className="text-sm font-semibold text-[#EF4444] flex items-center gap-2">
                      <AlertTriangle size={15} /> Étudiants à risque (taux &lt; {seuil}%)
                    </span>
                  </CardHeader>
                  <div className="divide-y divide-[rgba(255,255,255,0.04)]">
                    {Object.values(statsCls.etudiants_a_risque).map((e: any) => (
                      <div key={e.etudiant_id} className="flex items-center justify-between px-6 py-3">
                        <span className="text-sm text-[#F0F2FF]">Étudiant #{e.etudiant_id}</span>
                        <span className="text-sm font-mono text-[#EF4444] font-bold">{e.taux}%</span>
                      </div>
                    ))}
                  </div>
                </Card>
              )}
            </>
          )}
        </div>
      )}

      {/* Modal présence enseignant */}
      <Modal open={modalEns} onClose={() => setModalEns(false)} title="Présence enseignant" size="sm"
        footer={
          <>
            <Button variant="ghost" onClick={() => setModalEns(false)}>Annuler</Button>
            <Button loading={enseignantMut.isPending} onClick={() => enseignantMut.mutate(formEns)}>
              Enregistrer
            </Button>
          </>
        }>
        <div className="space-y-4">
          <Input label="ID Séance"     value={seanceId} onChange={e => setSeanceId(e.target.value)}         type="number" />
          <Input label="ID Enseignant" value={formEns.enseignant_id} onChange={e => setFormEns(f => ({ ...f, enseignant_id: e.target.value }))} type="number" />
          <Select label="Statut" value={formEns.statut} onChange={e => setFormEns(f => ({ ...f, statut: e.target.value }))}
            options={["Présent","Absent","Remplacé"].map(v => ({ value: v, label: v }))} />
          <Input label="Observations" value={formEns.observations} onChange={e => setFormEns(f => ({ ...f, observations: e.target.value }))} placeholder="Optionnel" />
        </div>
      </Modal>
    </div>
  );
}
