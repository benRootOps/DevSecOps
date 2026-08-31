// src/pages/financier/FinancierPage.tsx
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { DollarSign, PlusCircle, Receipt, TrendingUp, Wallet, Pencil, Search } from "lucide-react";
import { Card, CardHeader, CardBody, Modal, EmptyState, Input, Select, StatCard } from "../../components/ui/components";
import { Button, Badge, Skeleton, Avatar } from "../../components/ui/atoms";
import { PermissionGate } from "../../components/layout/AppShell";
import { toast } from "sonner";
import client from "../../api/client";
import { cn } from "../../utils/cn";

const TABS = ["Catégories & Frais", "Versements", "Rapport"];

// ⚠️ Champs déduits du schéma (categories_frais / frais / versements) — à
// ajuster selon les FormRequest exacts de FinancierController si différents.

function formatMontant(v: any) {
  if (v === null || v === undefined || v === "") return "—";
  return new Intl.NumberFormat("fr-FR").format(Number(v)) + " FCFA";
}

export default function FinancierPage() {
  const qc = useQueryClient();
  const [tab, setTab] = useState(0);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[#F0F2FF] flex items-center gap-2">
            <DollarSign size={20} className="text-[#F59E0B]" /> Financier
          </h1>
          <p className="text-sm text-[#5C6785] mt-0.5">Frais de scolarité, versements et rapports</p>
        </div>
      </div>

      <div className="flex gap-1 p-1 bg-[#111420] border border-[rgba(255,255,255,0.06)] rounded-xl w-fit">
        {TABS.map((t, i) => (
          <button key={i} onClick={() => setTab(i)}
            className={cn("px-4 py-2 text-xs font-medium rounded-lg transition-all",
              tab === i ? "bg-[rgba(245,158,11,0.14)] text-[#F0F2FF]" : "text-[#5C6785] hover:text-[#8A97B5]")}>
            {t}
          </button>
        ))}
      </div>

      {tab === 0 && <TabFrais qc={qc} />}
      {tab === 1 && <TabVersements qc={qc} />}
      {tab === 2 && <TabRapport />}
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 0 — Catégories & Frais
   ══════════════════════════════════════════════════════════════ */
function TabFrais({ qc }: { qc: ReturnType<typeof useQueryClient> }) {
  const [modalCat, setModalCat] = useState(false);
  const [formCat, setFormCat] = useState({ nom: "", description: "" });
  const [modalFrais, setModalFrais] = useState(false);
  const [editingFrais, setEditingFrais] = useState<any>(null);
  const [formFrais, setFormFrais] = useState({ categorie_frais_id: "", nom: "", montant: "", niveau_id: "", annee_academique_id: "1" });

  const { data: categories, isLoading: catLoading } = useQuery({
    queryKey: ["categories-frais"],
    queryFn: () => client.get("/categories-frais").then(r => r.data.donnees),
  });

  const { data: frais, isLoading: fraisLoading } = useQuery({
    queryKey: ["frais"],
    queryFn: () => client.get("/frais").then(r => r.data.donnees),
  });

  const createCatMut = useMutation({
    mutationFn: (d: typeof formCat) => client.post("/categories-frais", d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["categories-frais"] });
      setModalCat(false); setFormCat({ nom: "", description: "" });
      toast.success("Catégorie créée.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const saveFraisMut = useMutation({
    mutationFn: (d: typeof formFrais) => {
      const payload = { ...d, categorie_frais_id: Number(d.categorie_frais_id), montant: Number(d.montant), niveau_id: d.niveau_id ? Number(d.niveau_id) : undefined, annee_academique_id: Number(d.annee_academique_id) };
      return editingFrais ? client.put(`/frais/${editingFrais.id}`, payload) : client.post("/frais", payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["frais"] });
      setModalFrais(false); setEditingFrais(null);
      setFormFrais({ categorie_frais_id: "", nom: "", montant: "", niveau_id: "", annee_academique_id: "1" });
      toast.success(editingFrais ? "Frais modifié." : "Frais créé.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const catOptions = (categories ?? []).map((c: any) => ({ value: String(c.id), label: c.nom }));

  return (
    <div className="space-y-6">
      {/* Catégories */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <span className="text-sm font-semibold text-[#F0F2FF]">Catégories de frais</span>
            <PermissionGate permission="financier.frais.creer">
              <Button size="sm" variant="outline" icon={<PlusCircle size={13} />} onClick={() => setModalCat(true)}>
                Nouvelle catégorie
              </Button>
            </PermissionGate>
          </div>
        </CardHeader>
        <CardBody>
          {catLoading ? (
            <div className="flex gap-2">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-8 w-32 rounded-full" />)}</div>
          ) : categories?.length === 0 ? (
            <p className="text-xs text-[#5C6785]">Aucune catégorie de frais.</p>
          ) : (
            <div className="flex flex-wrap gap-2">
              {categories?.map((c: any) => (
                <span key={c.id} className="px-3 py-1.5 rounded-full text-xs font-medium bg-[rgba(245,158,11,0.1)] text-[#F59E0B] border border-[rgba(245,158,11,0.25)]">
                  {c.nom}
                </span>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Frais */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <span className="text-sm font-semibold text-[#F0F2FF]">Frais définis</span>
            <PermissionGate permission="financier.frais.creer">
              <Button size="sm" variant="outline" icon={<PlusCircle size={13} />}
                onClick={() => { setEditingFrais(null); setModalFrais(true); }}>
                Nouveau frais
              </Button>
            </PermissionGate>
          </div>
        </CardHeader>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Nom", "Catégorie", "Montant", "Niveau", ""].map(h => (
                  <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {fraisLoading
                ? Array.from({ length: 3 }).map((_, i) => (
                    <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                      {[1, 2, 3, 4, 5].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                    </tr>
                  ))
                : frais?.length === 0
                ? <tr><td colSpan={5}><EmptyState icon={<Receipt size={24} />} title="Aucun frais" description="Définissez le premier frais de scolarité." /></td></tr>
                : frais?.map((f: any) => (
                    <tr key={f.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                      <td className="px-6 py-3 text-sm text-[#F0F2FF]">{f.nom}</td>
                      <td className="px-6 py-3 text-xs text-[#5C6785]">{f.categorie?.nom ?? f.categorie_frais_id}</td>
                      <td className="px-6 py-3 text-sm font-mono font-bold text-[#F59E0B]">{formatMontant(f.montant)}</td>
                      <td className="px-6 py-3 text-xs text-[#5C6785]">{f.niveau?.nom ?? "Tous niveaux"}</td>
                      <td className="px-6 py-3 text-right">
                        <PermissionGate permission="financier.frais.modifier">
                          <button
                            onClick={() => { setEditingFrais(f); setFormFrais({ categorie_frais_id: String(f.categorie_frais_id ?? ""), nom: f.nom ?? "", montant: String(f.montant ?? ""), niveau_id: f.niveau_id ? String(f.niveau_id) : "", annee_academique_id: String(f.annee_academique_id ?? "1") }); setModalFrais(true); }}
                            className="p-1.5 rounded-lg text-[#5C6785] hover:text-[#F0F2FF] hover:bg-[rgba(255,255,255,0.06)]">
                            <Pencil size={13} />
                          </button>
                        </PermissionGate>
                      </td>
                    </tr>
                  ))}
            </tbody>
          </table>
        </div>
      </Card>

      {/* Modal catégorie */}
      <Modal open={modalCat} onClose={() => setModalCat(false)} title="Nouvelle catégorie de frais" size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalCat(false)}>Annuler</Button><Button loading={createCatMut.isPending} onClick={() => createCatMut.mutate(formCat)}>Créer</Button></>}>
        <div className="space-y-4">
          <Input label="Nom" placeholder="Ex: Scolarité, Inscription…" value={formCat.nom} onChange={e => setFormCat(f => ({ ...f, nom: e.target.value }))} />
          <Input label="Description" placeholder="Optionnel" value={formCat.description} onChange={e => setFormCat(f => ({ ...f, description: e.target.value }))} />
        </div>
      </Modal>

      {/* Modal frais */}
      <Modal open={modalFrais} onClose={() => setModalFrais(false)} title={editingFrais ? "Modifier le frais" : "Nouveau frais"} size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalFrais(false)}>Annuler</Button><Button loading={saveFraisMut.isPending} onClick={() => saveFraisMut.mutate(formFrais)}>{editingFrais ? "Enregistrer" : "Créer"}</Button></>}>
        <div className="space-y-4">
          <Select label="Catégorie" value={formFrais.categorie_frais_id} onChange={e => setFormFrais(f => ({ ...f, categorie_frais_id: e.target.value }))}
            options={[{ value: "", label: "Sélectionner…" }, ...catOptions]} />
          <Input label="Nom" value={formFrais.nom} onChange={e => setFormFrais(f => ({ ...f, nom: e.target.value }))} />
          <Input label="Montant (FCFA)" type="number" value={formFrais.montant} onChange={e => setFormFrais(f => ({ ...f, montant: e.target.value }))} />
          <Input label="Niveau ID" type="number" placeholder="Optionnel — tous niveaux si vide" value={formFrais.niveau_id} onChange={e => setFormFrais(f => ({ ...f, niveau_id: e.target.value }))} />
          <Input label="Année académique ID" type="number" value={formFrais.annee_academique_id} onChange={e => setFormFrais(f => ({ ...f, annee_academique_id: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 1 — Versements
   ══════════════════════════════════════════════════════════════ */
function TabVersements({ qc }: { qc: ReturnType<typeof useQueryClient> }) {
  const [etuId, setEtuId] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ etudiant_id: "", frais_id: "", montant: "", moyen_paiement: "Espèces", date_versement: "" });

  const { data: versements, isLoading } = useQuery({
    queryKey: ["versements", etuId],
    queryFn: () => client.get("/versements", { params: etuId ? { etudiant_id: etuId } : {} }).then(r => r.data.donnees),
  });

  const { data: situation, isFetching: situationLoading } = useQuery({
    queryKey: ["situation-financiere", etuId],
    queryFn: () => client.get(`/etudiants/${etuId}/situation-financiere`).then(r => r.data.donnees),
    enabled: !!etuId,
  });

  const createMut = useMutation({
    mutationFn: (d: typeof form) => client.post("/versements", { ...d, etudiant_id: Number(d.etudiant_id), frais_id: Number(d.frais_id), montant: Number(d.montant) }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["versements"] });
      qc.invalidateQueries({ queryKey: ["situation-financiere"] });
      setModalOpen(false);
      setForm({ etudiant_id: "", frais_id: "", montant: "", moyen_paiement: "Espèces", date_versement: "" });
      toast.success("Versement enregistré.");
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? "Erreur"),
  });

  const rows: any[] = versements ?? [];

  return (
    <div className="space-y-4">
      <div className="flex items-end gap-3 flex-wrap">
        <Input label="Filtrer par étudiant ID" value={etuId} onChange={e => setEtuId(e.target.value)} type="number" className="max-w-[200px]" icon={<Search size={14} />} />
        <PermissionGate permission="financier.saisir">
          <Button icon={<PlusCircle size={15} />} className="ml-auto" onClick={() => setModalOpen(true)}>
            Nouveau versement
          </Button>
        </PermissionGate>
      </div>

      {etuId && situation && (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          <StatCard label="Total dû" value={formatMontant(situation.total_du ?? situation.montant_du)} icon={<Wallet size={18} />} color="#F59E0B" loading={situationLoading} />
          <StatCard label="Total payé" value={formatMontant(situation.total_paye ?? situation.montant_paye)} icon={<Receipt size={18} />} color="#10B981" loading={situationLoading} />
          <StatCard label="Solde restant" value={formatMontant(situation.solde ?? situation.reste_a_payer)} icon={<DollarSign size={18} />} color="#EF4444" loading={situationLoading} />
        </div>
      )}

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-[rgba(255,255,255,0.05)]">
                {["Étudiant", "Frais", "Montant", "Moyen", "Date", "Reçu"].map(h => (
                  <th key={h} className="px-6 py-3 text-left text-[10px] font-semibold text-[#5C6785] uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {isLoading
                ? Array.from({ length: 5 }).map((_, i) => (
                    <tr key={i} className="border-b border-[rgba(255,255,255,0.03)]">
                      {[1, 2, 3, 4, 5, 6].map(j => <td key={j} className="px-6 py-4"><Skeleton className="h-4 rounded" /></td>)}
                    </tr>
                  ))
                : rows.length === 0
                ? <tr><td colSpan={6}><EmptyState icon={<Wallet size={24} />} title="Aucun versement" description="Enregistrez le premier versement." /></td></tr>
                : rows.map((v: any) => (
                    <tr key={v.id} className="border-b border-[rgba(255,255,255,0.03)] hover:bg-[rgba(255,255,255,0.02)] transition-colors">
                      <td className="px-6 py-3">
                        <div className="flex items-center gap-3">
                          <Avatar nom={v.etudiant?.nom} prenom={v.etudiant?.prenom} size="sm" />
                          <span className="text-sm text-[#F0F2FF]">{v.etudiant?.prenom} {v.etudiant?.nom}</span>
                        </div>
                      </td>
                      <td className="px-6 py-3 text-xs text-[#5C6785]">{v.frais?.nom ?? v.frais_id}</td>
                      <td className="px-6 py-3 text-sm font-mono font-bold text-[#10B981]">{formatMontant(v.montant)}</td>
                      <td className="px-6 py-3"><Badge color="#06B6D4">{v.moyen_paiement}</Badge></td>
                      <td className="px-6 py-3 text-xs text-[#5C6785]">{v.date_versement}</td>
                      <td className="px-6 py-3 text-xs text-[#5C6785] font-mono">{v.numero_recu ?? v.recu?.numero ?? "—"}</td>
                    </tr>
                  ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nouveau versement" size="sm"
        footer={<><Button variant="ghost" onClick={() => setModalOpen(false)}>Annuler</Button><Button loading={createMut.isPending} onClick={() => createMut.mutate(form)}>Enregistrer</Button></>}>
        <div className="space-y-4">
          <Input label="Étudiant ID" type="number" value={form.etudiant_id} onChange={e => setForm(f => ({ ...f, etudiant_id: e.target.value }))} />
          <Input label="Frais ID" type="number" value={form.frais_id} onChange={e => setForm(f => ({ ...f, frais_id: e.target.value }))} />
          <Input label="Montant (FCFA)" type="number" value={form.montant} onChange={e => setForm(f => ({ ...f, montant: e.target.value }))} />
          <Select label="Moyen de paiement" value={form.moyen_paiement} onChange={e => setForm(f => ({ ...f, moyen_paiement: e.target.value }))}
            options={["Espèces", "Virement", "MTN MoMo", "Orange Money", "Chèque"].map(v => ({ value: v, label: v }))} />
          <Input label="Date" type="date" value={form.date_versement} onChange={e => setForm(f => ({ ...f, date_versement: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}

/* ══════════════════════════════════════════════════════════════
   TAB 2 — Rapport financier
   ══════════════════════════════════════════════════════════════ */
function TabRapport() {
  const [anneeId, setAnneeId] = useState("1");

  const { data: rapport, isLoading } = useQuery({
    queryKey: ["rapport-financier", anneeId],
    queryFn: () => client.get("/rapports/financier", { params: { annee_academique_id: anneeId } }).then(r => r.data.donnees),
  });

  const detail: any[] = rapport?.par_categorie ?? rapport?.detail ?? [];

  return (
    <div className="space-y-4">
      <div className="flex items-end gap-3">
        <Input label="Année académique ID" type="number" value={anneeId} onChange={e => setAnneeId(e.target.value)} className="max-w-[180px]" />
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">{Array.from({ length: 3 }).map((_, i) => <StatCard key={i} label="" value="" icon={<DollarSign size={18} />} color="#F59E0B" loading />)}</div>
      ) : !rapport ? (
        <Card><CardBody><EmptyState icon={<TrendingUp size={24} />} title="Aucune donnée" description="Aucun rapport disponible pour cette année." /></CardBody></Card>
      ) : (
        <>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            <StatCard label="Total attendu" value={formatMontant(rapport.total_attendu)} icon={<Wallet size={18} />} color="#F59E0B" />
            <StatCard label="Total encaissé" value={formatMontant(rapport.total_encaisse)} icon={<Receipt size={18} />} color="#10B981" />
            <StatCard label="Taux de recouvrement" value={`${rapport.taux_recouvrement ?? 0}%`} icon={<TrendingUp size={18} />} color="#06B6D4" />
          </div>

          {detail.length > 0 && (
            <Card>
              <CardHeader><span className="text-sm font-semibold text-[#F0F2FF]">Détail par catégorie</span></CardHeader>
              <div className="divide-y divide-[rgba(255,255,255,0.04)]">
                {detail.map((d: any, i: number) => (
                  <div key={i} className="flex items-center justify-between px-6 py-3">
                    <span className="text-sm text-[#F0F2FF]">{d.categorie ?? d.nom}</span>
                    <div className="flex items-center gap-4">
                      <span className="text-xs text-[#5C6785] font-mono">{formatMontant(d.encaisse ?? d.montant)}</span>
                      <span className="text-xs font-mono" style={{ color: (d.taux ?? 0) >= 75 ? "#10B981" : "#F59E0B" }}>{d.taux ?? "—"}%</span>
                    </div>
                  </div>
                ))}
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
