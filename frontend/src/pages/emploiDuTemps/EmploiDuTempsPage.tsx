// src/pages/emploiDuTemps/EmploiDuTempsPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Calendar, Plus, MapPin, Clock, AlertTriangle, CheckCircle } from "lucide-react";
import { Card, CardHeader, CardBody, Modal, Input, Select, EmptyState } from "../../components/ui/components";
import { Button, Badge, Skeleton } from "../../components/ui/atoms";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";
import { cn } from "../../utils/cn";

const JOURS = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi"];
const JOURS_OPTIONS = JOURS.map((j, i) => ({ value: String(i + 1), label: j }));

const TYPE_SEANCE_COLORS: Record<string, string> = {
  Cours: "#7C6AF7", TD: "#06B6D4", TP: "#10B981", Examen: "#EF4444",
};

export default function EmploiDuTempsPage() {
  const qc = useQueryClient();
  const [tab, setTab]           = useState<"edt" | "salles" | "creneaux" | "conflits">("edt");
  const [classeId, setClasseId] = useState("1");
  const [semestreId, setSemId]  = useState("1");
  const [modalSalle,  setModalSalle]  = useState(false);
  const [modalCreneau,setModalCreneau]= useState(false);
  const [modalSeance, setModalSeance] = useState(false);

  const [formSalle,   setFormSalle]   = useState({ nom: "", batiment: "", capacite: "", type_salle: "Salle de cours" });
  const [formCreneau, setFormCreneau] = useState({ heure_debut: "08:00", heure_fin: "10:00", libelle: "", ordre: "1" });
  const [formSeance,  setFormSeance]  = useState({ affectation_id: "", salle_id: "", classe_id: classeId, semestre_id: semestreId, creneau_id: "", jour_semaine: "1", type_seance: "Cours" });

  // Queries
  const { data: edt, isLoading: edtLoading } = useQuery({
    queryKey: ["edt", classeId, semestreId],
    queryFn: () => client.get(`/emploi-du-temps/classe/${classeId}/semestre/${semestreId}`).then(r => r.data.donnees),
    enabled: tab === "edt" && !!classeId && !!semestreId,
  });

  const { data: salles, isLoading: sallesLoading } = useQuery({
    queryKey: ["salles"],
    queryFn: () => client.get("/salles").then(r => r.data.donnees),
    enabled: tab === "salles" || tab === "edt",
  });

  const { data: creneaux } = useQuery({
    queryKey: ["creneaux"],
    queryFn: () => client.get("/creneaux").then(r => r.data.donnees),
    enabled: tab === "creneaux" || tab === "edt",
  });

  const { data: conflits } = useQuery({
    queryKey: ["conflits"],
    queryFn: () => client.get("/conflits").then(r => r.data.donnees),
    enabled: tab === "conflits",
  });

  // Mutations
  const createSalle = useMutation({
    mutationFn: (d: typeof formSalle) => client.post("/salles", { ...d, capacite: Number(d.capacite) || null }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["salles"] }); setModalSalle(false); toast.success("Salle créée."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const createCreneau = useMutation({
    mutationFn: (d: typeof formCreneau) => client.post("/creneaux", { ...d, ordre: Number(d.ordre) }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["creneaux"] }); setModalCreneau(false); toast.success("Créneau créé."); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const createSeance = useMutation({
    mutationFn: (d: typeof formSeance) => client.post("/seances", {
      ...d,
      affectation_id: Number(d.affectation_id),
      salle_id: Number(d.salle_id) || null,
      classe_id: Number(d.classe_id),
      semestre_id: Number(d.semestre_id),
      creneau_id: Number(d.creneau_id),
      jour_semaine: Number(d.jour_semaine),
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["edt"] });
      qc.invalidateQueries({ queryKey: ["conflits"] });
      setModalSeance(false);
      toast.success("Séance planifiée.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const resoudreConflit = useMutation({
    mutationFn: (id: number) => client.patch(`/conflits/${id}/resoudre`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["conflits"] }); toast.success("Conflit résolu."); },
  });

  const TABS = [
    { key: "edt",      label: "Emploi du temps", icon: Calendar },
    { key: "salles",   label: "Salles",           icon: MapPin },
    { key: "creneaux", label: "Créneaux",          icon: Clock },
    { key: "conflits", label: "Conflits",          icon: AlertTriangle },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
          <Calendar size={20} className="text-[#06B6D4]" /> Emploi du temps
        </h1>
        <div className="flex gap-2">
          <PermissionGate permission="emploi_temps.creer">
            {tab === "salles"   && <Button icon={<Plus size={14} />} onClick={() => setModalSalle(true)}>Nouvelle salle</Button>}
            {tab === "creneaux" && <Button icon={<Plus size={14} />} onClick={() => setModalCreneau(true)}>Nouveau créneau</Button>}
            {tab === "edt"      && <Button icon={<Plus size={14} />} onClick={() => setModalSeance(true)}>Planifier séance</Button>}
          </PermissionGate>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl w-fit">
        {TABS.map(t => (
          <button key={t.key} onClick={() => setTab(t.key as any)}
            className={cn("flex items-center gap-2 px-4 py-2 text-xs font-medium rounded-lg transition-all",
              tab === t.key ? "bg-[rgba(6,182,212,0.12)] text-[#F0F2FF]" : "text-[#5C6785] hover:text-[#8A97B5]")}>
            <t.icon size={13} /> {t.label}
          </button>
        ))}
      </div>

      {/* EDT */}
      {tab === "edt" && (
        <div className="space-y-4">
          <div className="flex gap-3">
            <Input label="Classe ID" value={classeId} onChange={e => setClasseId(e.target.value)} type="number" className="max-w-[120px]" />
            <Input label="Semestre ID" value={semestreId} onChange={e => setSemId(e.target.value)} type="number" className="max-w-[120px]" />
          </div>
          {edtLoading ? (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-40 rounded-xl" />)}
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {JOURS.map(jour => {
                const seances: any[] = edt?.[jour] ?? [];
                return (
                  <Card key={jour}>
                    <CardHeader>
                      <div className="flex items-center justify-between">
                        <span className="text-sm font-semibold text-[#F0F2FF]">{jour}</span>
                        <Badge color="#06B6D4">{seances.length} séance{seances.length !== 1 ? "s" : ""}</Badge>
                      </div>
                    </CardHeader>
                    <CardBody className="p-3 space-y-2">
                      {seances.length === 0
                        ? <p className="text-xs text-[#5C6785] text-center py-4">Aucune séance</p>
                        : seances.map((s: any) => {
                            const color = TYPE_SEANCE_COLORS[s.type_seance] ?? "#7C6AF7";
                            return (
                              <div key={s.id} className="p-3 rounded-lg border"
                                style={{ background: `${color}08`, borderColor: `${color}20` }}>
                                <div className="flex items-center justify-between mb-1">
                                  <span className="text-xs font-semibold" style={{ color }}>
                                    {s.type_seance}
                                  </span>
                                  <span className="text-[10px] text-[#5C6785] font-mono">
                                    {s.creneau?.heure_debut} – {s.creneau?.heure_fin}
                                  </span>
                                </div>
                                <div className="text-xs text-[#F0F2FF] font-medium">
                                  {s.affectation?.matiere?.intitule ?? "—"}
                                </div>
                                <div className="text-[10px] text-[#5C6785] mt-0.5">
                                  {s.affectation?.enseignant?.utilisateur?.nom} · {s.salle?.nom ?? "Sans salle"}
                                </div>
                              </div>
                            );
                          })}
                    </CardBody>
                  </Card>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Salles */}
      {tab === "salles" && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {sallesLoading
            ? Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-28 rounded-xl" />)
            : salles?.data?.length === 0
            ? <EmptyState icon={<MapPin size={24} />} title="Aucune salle" description="Créez votre première salle." />
            : salles?.data?.map((s: any) => (
                <Card key={s.id} className="p-4">
                  <div className="flex items-start justify-between">
                    <div>
                      <div className="text-sm font-semibold text-[#F0F2FF]">{s.nom}</div>
                      <div className="text-xs text-[#5C6785] mt-1">{s.batiment} · {s.type_salle}</div>
                      <div className="text-xs text-[#5C6785]">Capacité : {s.capacite ?? "—"}</div>
                    </div>
                    <span className={cn("text-xs px-2 py-0.5 rounded-full",
                      s.est_disponible ? "bg-[rgba(16,185,129,0.1)] text-[#10B981]" : "bg-[rgba(239,68,68,0.1)] text-[#EF4444]")}>
                      {s.est_disponible ? "Disponible" : "Occupée"}
                    </span>
                  </div>
                </Card>
              ))}
        </div>
      )}

      {/* Créneaux */}
      {tab === "creneaux" && (
        <Card>
          <div className="divide-y divide-[rgba(255,255,255,0.04)]">
            {creneaux?.data?.map((c: any) => (
              <div key={c.id} className="flex items-center justify-between px-6 py-4 hover:bg-[rgba(255,255,255,0.02)]">
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-[rgba(6,182,212,0.1)] flex items-center justify-center text-[#06B6D4] text-xs font-bold">
                    {c.ordre}
                  </div>
                  <div>
                    <div className="text-sm font-medium text-[#F0F2FF]">{c.libelle ?? `Créneau ${c.ordre}`}</div>
                    <div className="text-xs text-[#5C6785] font-mono">{c.heure_debut} – {c.heure_fin}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* Conflits */}
      {tab === "conflits" && (
        <div className="space-y-3">
          {conflits?.length === 0
            ? <Card><EmptyState icon={<CheckCircle size={24} />} title="Aucun conflit" description="L'emploi du temps est cohérent." /></Card>
            : conflits?.map((c: any) => (
                <div key={c.id} className="bg-[rgba(239,68,68,0.06)] border border-[rgba(239,68,68,0.2)] rounded-xl p-4 flex items-center justify-between">
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <AlertTriangle size={14} className="text-[#EF4444]" />
                      <span className="text-sm font-semibold text-[#EF4444]">{c.type_conflit}</span>
                    </div>
                    <p className="text-xs text-[#8A97B5]">{c.detail}</p>
                  </div>
                  <PermissionGate permission="emploi_temps.modifier">
                    <Button size="sm" variant="success" onClick={() => resoudreConflit.mutate(c.id)}>
                      Résoudre
                    </Button>
                  </PermissionGate>
                </div>
              ))}
        </div>
      )}

      {/* Modal Salle */}
      <Modal open={modalSalle} onClose={() => setModalSalle(false)} title="Nouvelle salle" size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalSalle(false)}>Annuler</Button>
          <Button loading={createSalle.isPending} onClick={() => createSalle.mutate(formSalle)}>Créer</Button></>}>
        <div className="space-y-4">
          <Input label="Nom" value={formSalle.nom} onChange={e => setFormSalle(f => ({ ...f, nom: e.target.value }))} />
          <Input label="Bâtiment" value={formSalle.batiment} onChange={e => setFormSalle(f => ({ ...f, batiment: e.target.value }))} />
          <Input label="Capacité" type="number" value={formSalle.capacite} onChange={e => setFormSalle(f => ({ ...f, capacite: e.target.value }))} />
          <Select label="Type" value={formSalle.type_salle} onChange={e => setFormSalle(f => ({ ...f, type_salle: e.target.value }))}
            options={["Amphithéâtre","Salle de cours","Salle TP","Salle TD"].map(v => ({ value: v, label: v }))} />
        </div>
      </Modal>

      {/* Modal Créneau */}
      <Modal open={modalCreneau} onClose={() => setModalCreneau(false)} title="Nouveau créneau" size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalCreneau(false)}>Annuler</Button>
          <Button loading={createCreneau.isPending} onClick={() => createCreneau.mutate(formCreneau)}>Créer</Button></>}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Input label="Heure début" type="time" value={formCreneau.heure_debut} onChange={e => setFormCreneau(f => ({ ...f, heure_debut: e.target.value }))} />
            <Input label="Heure fin" type="time" value={formCreneau.heure_fin} onChange={e => setFormCreneau(f => ({ ...f, heure_fin: e.target.value }))} />
          </div>
          <Input label="Libellé" value={formCreneau.libelle} onChange={e => setFormCreneau(f => ({ ...f, libelle: e.target.value }))} placeholder="Ex: Matin 1" />
          <Input label="Ordre" type="number" value={formCreneau.ordre} onChange={e => setFormCreneau(f => ({ ...f, ordre: e.target.value }))} />
        </div>
      </Modal>

      {/* Modal Séance */}
      <Modal open={modalSeance} onClose={() => setModalSeance(false)} title="Planifier une séance" size="md"
        footer={<><Button variant="ghost" onClick={() => setModalSeance(false)}>Annuler</Button>
          <Button loading={createSeance.isPending} onClick={() => createSeance.mutate(formSeance)}>Planifier</Button></>}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Input label="Affectation ID" type="number" value={formSeance.affectation_id} onChange={e => setFormSeance(f => ({ ...f, affectation_id: e.target.value }))} />
            <Input label="Salle ID" type="number" value={formSeance.salle_id} onChange={e => setFormSeance(f => ({ ...f, salle_id: e.target.value }))} />
            <Input label="Classe ID" type="number" value={formSeance.classe_id} onChange={e => setFormSeance(f => ({ ...f, classe_id: e.target.value }))} />
            <Input label="Semestre ID" type="number" value={formSeance.semestre_id} onChange={e => setFormSeance(f => ({ ...f, semestre_id: e.target.value }))} />
            <Input label="Créneau ID" type="number" value={formSeance.creneau_id} onChange={e => setFormSeance(f => ({ ...f, creneau_id: e.target.value }))} />
            <Select label="Jour" value={formSeance.jour_semaine} onChange={e => setFormSeance(f => ({ ...f, jour_semaine: e.target.value }))} options={JOURS_OPTIONS} />
          </div>
          <Select label="Type de séance" value={formSeance.type_seance} onChange={e => setFormSeance(f => ({ ...f, type_seance: e.target.value }))}
            options={["Cours","TD","TP","Examen"].map(v => ({ value: v, label: v }))} />
        </div>
      </Modal>
    </div>
  );
}
