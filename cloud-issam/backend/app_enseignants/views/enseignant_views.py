"""
Vues pour le dashboard enseignant
- Profil
- Récupération des niveaux, filières, spécialités, matières enseignés
- Gestion des notes
- Gestion des affectations (niveau/filière/spécialité/matière)
"""

from rest_framework.decorators import api_view, permission_classes
from rest_framework.response import Response
from rest_framework.permissions import IsAuthenticated
from rest_framework import status
import logging

CREDITS_REQUIS_SEMESTRE = 30


logger=logging.basicConfig(level=logging.WARNING,format=" %(levelname)s - %(message)s")
from ..models import (
    Enseignant, EnseignantNiveau, EnseignantSpecialite, EnseignantMatiere,
    Niveau, Filiere, Specialite, Matiere, Etudiant, Note,EmploiDuTemps
)
from ..serializers import (
    EnseignantProfileSerializer,
    EtudiantSerializer,
    NoteSerializer,
    SaveNotesSerializer,
    NiveauSerializer,
    FiliereSerializer,
    SpecialiteSerializer,
    MatiereSerializer,EmploiDuTempsSerializer,
)


# ─── Profil ────────────────────────────────────────────────────────────────
@api_view(['GET'])
@permission_classes([IsAuthenticated])
def enseignant_profile(request):
    try:
        enseignant = request.user.enseignant_profile
        serializer = EnseignantProfileSerializer(enseignant)
        return Response(serializer.data)
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


# ─── Données enseignées (lecture) ──────────────────────────────────────────
@api_view(['GET'])
@permission_classes([IsAuthenticated])
def enseignant_niveaux(request):
    """Niveaux enseignés — retourne objets complets avec id+nom"""
    try:
        enseignant = request.user.enseignant_profile
        niveaux_ids = EnseignantNiveau.objects.filter(
            enseignant=enseignant
        ).values_list('niveau_id', flat=True).distinct()

        niveaux = Niveau.objects.filter(id__in=niveaux_ids)
        return Response({'niveaux': NiveauSerializer(niveaux, many=True).data})
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


@api_view(['GET'])
@permission_classes([IsAuthenticated])
def enseignant_filieres(request):
    """Filières pour un niveau donné (par nom)"""
    niveau_nom = request.query_params.get('niveau')
    if not niveau_nom:
        return Response({'error': 'Paramètre "niveau" requis.'}, status=400)

    try:
        enseignant = request.user.enseignant_profile
        filieres_ids = EnseignantNiveau.objects.filter(
            enseignant=enseignant,
            niveau__nom=niveau_nom
        ).values_list('filiere_id', flat=True).distinct()

        filieres = Filiere.objects.filter(id__in=filieres_ids)
        return Response({'filieres': FiliereSerializer(filieres, many=True).data})
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


@api_view(['GET'])
@permission_classes([IsAuthenticated])
def enseignant_specialites(request):
    """Spécialités pour une filière donnée"""
    filiere_nom = request.query_params.get('filiere')
    if not filiere_nom:
        return Response({'error': 'Paramètre "filiere" requis.'}, status=400)

    try:
        enseignant = request.user.enseignant_profile
        specialites_ids = EnseignantSpecialite.objects.filter(
            enseignant=enseignant,
            specialite__filiere__nom=filiere_nom
        ).values_list('specialite_id', flat=True).distinct()

        specialites = Specialite.objects.filter(id__in=specialites_ids)
        return Response({'specialites': SpecialiteSerializer(specialites, many=True).data})
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


@api_view(['GET'])
@permission_classes([IsAuthenticated])
def enseignant_matieres(request):
    """Matières pour un niveau, filière et spécialité"""
    niveau_nom    = request.query_params.get('niveau')
    filiere_nom   = request.query_params.get('filiere')
    specialite_nom = request.query_params.get('specialite')

    if not all([niveau_nom, filiere_nom, specialite_nom]):
        return Response({'error': 'Paramètres "niveau", "filiere" et "specialite" requis.'}, status=400)

    try:
        print("*****" * 3)
        print('matiere retourner OKay')
        enseignant = request.user.enseignant_profile
        matieres = Matiere.objects.filter(
            enseignants__enseignant=enseignant,
            niveau__nom=niveau_nom,
            filiere__nom=filiere_nom,
            specialites__nom=specialite_nom
        ).distinct()

        return Response({'matieres': MatiereSerializer(matieres, many=True).data})
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


# ─── Affectations — lecture complète ──────────────────────────────────────
@api_view(['GET'])
@permission_classes([IsAuthenticated])
def enseignant_affectations(request):
    """
    Retourne toutes les affectations de l'enseignant connecté :
    niveaux/filières, spécialités et matières assignées.
    Utilisé par le composant de gestion des affectations.
    """
    try:
        enseignant = request.user.enseignant_profile

        # Niveaux + filières
        niveaux_filieres = EnseignantNiveau.objects.filter(
            enseignant=enseignant
        ).select_related('niveau', 'filiere')
        nf_data = [{
            'id': nf.id,
            'niveau': {'id': nf.niveau.id, 'nom': nf.niveau.nom},
            'filiere': {'id': nf.filiere.id, 'nom': nf.filiere.nom},
        } for nf in niveaux_filieres]

        # Spécialités
        specialites = EnseignantSpecialite.objects.filter(
            enseignant=enseignant
        ).select_related('specialite', 'specialite__filiere')
        sp_data = [{
            'id': sp.id,
            'specialite': {
                'id': sp.specialite.id,
                'nom': sp.specialite.nom,
                'filiere_nom': sp.specialite.filiere.nom,
            },
        } for sp in specialites]

        # Matières
        matieres = EnseignantMatiere.objects.filter(
            enseignant=enseignant
        ).select_related('matiere', 'matiere__niveau', 'matiere__filiere')
        mat_data = [{
            'id': em.id,
            'matiere': {
                'id': em.matiere.id,
                'nom': em.matiere.nom,
                'code': em.matiere.code,
                'coefficient': float(em.matiere.coefficient),
                'niveau_nom': em.matiere.niveau.nom,
                'filiere_nom': em.matiere.filiere.nom,
            },
        } for em in matieres]

        return Response({
            'niveaux_filieres': nf_data,
            'specialites': sp_data,
            'matieres': mat_data,
        })
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


# ─── Affectations — ajout ─────────────────────────────────────────────────
@api_view(['POST'])
@permission_classes([IsAuthenticated])
def ajouter_niveau_filiere(request):
    """
    Ajoute une liaison enseignant ↔ niveau + filière.
    Body : { "niveau_id": 1, "filiere_id": 3 }
    """
    try:
        enseignant = request.user.enseignant_profile
        niveau_id  = request.data.get('niveau_id')
        filiere_id = request.data.get('filiere_id')

        if not niveau_id or not filiere_id:
            return Response({'error': 'niveau_id et filiere_id sont requis.'}, status=400)

        niveau  = Niveau.objects.get(id=niveau_id)
        filiere = Filiere.objects.get(id=filiere_id)

        _, created = EnseignantNiveau.objects.get_or_create(
            enseignant=enseignant, niveau=niveau, filiere=filiere
        )

        if not created:
            return Response({'error': 'Cette affectation existe déjà.'}, status=400)

        return Response({
            'message': f'Affecté à {niveau.nom} — {filiere.nom}.',
        }, status=201)

    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)
    except (Niveau.DoesNotExist, Filiere.DoesNotExist):
        return Response({'error': 'Niveau ou filière introuvable.'}, status=404)


@api_view(['POST'])
@permission_classes([IsAuthenticated])
def ajouter_specialite(request):
    """
    Ajoute une liaison enseignant ↔ spécialité.
    Body : { "specialite_id": 5 }
    """
    try:
        enseignant    = request.user.enseignant_profile
        specialite_id = request.data.get('specialite_id')

        if not specialite_id:
            return Response({'error': 'specialite_id est requis.'}, status=400)

        specialite = Specialite.objects.get(id=specialite_id)

        _, created = EnseignantSpecialite.objects.get_or_create(
            enseignant=enseignant, specialite=specialite
        )

        if not created:
            return Response({'error': 'Cette spécialité est déjà assignée.'}, status=400)

        return Response({'message': f'Spécialité "{specialite.nom}" ajoutée.'}, status=201)

    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)
    except Specialite.DoesNotExist:
        return Response({'error': 'Spécialité introuvable.'}, status=404)


@api_view(['POST'])
@permission_classes([IsAuthenticated])
def ajouter_matiere(request):
    """
    Ajoute une liaison enseignant ↔ matière.
    Body : { "matiere_id": 12 }
    """
    try:
        enseignant = request.user.enseignant_profile
        matiere_id = request.data.get('matiere_id')

        if not matiere_id:
            return Response({'error': 'matiere_id est requis.'}, status=400)

        matiere = Matiere.objects.get(id=matiere_id)

        _, created = EnseignantMatiere.objects.get_or_create(
            enseignant=enseignant, matiere=matiere
        )

        if not created:
            return Response({'error': 'Cette matière est déjà assignée.'}, status=400)

        return Response({'message': f'Matière "{matiere.nom}" ajoutée.'}, status=201)

    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)
    except Matiere.DoesNotExist:
        return Response({'error': 'Matière introuvable.'}, status=404)


# ─── Affectations — suppression ───────────────────────────────────────────
@api_view(['DELETE'])
@permission_classes([IsAuthenticated])
def supprimer_niveau_filiere(request, pk):
    """Supprime une liaison enseignant ↔ niveau+filière"""
    try:
        enseignant = request.user.enseignant_profile
        lien = EnseignantNiveau.objects.get(id=pk, enseignant=enseignant)
        lien.delete()
        return Response({'message': 'Affectation supprimée.'})
    except EnseignantNiveau.DoesNotExist:
        return Response({'error': 'Affectation non trouvée.'}, status=404)
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


@api_view(['DELETE'])
@permission_classes([IsAuthenticated])
def supprimer_specialite(request, pk):
    """Supprime une liaison enseignant ↔ spécialité"""
    try:
        enseignant = request.user.enseignant_profile
        lien = EnseignantSpecialite.objects.get(id=pk, enseignant=enseignant)
        lien.delete()
        return Response({'message': 'Spécialité retirée.'})
    except EnseignantSpecialite.DoesNotExist:
        return Response({'error': 'Affectation non trouvée.'}, status=404)
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


@api_view(['DELETE'])
@permission_classes([IsAuthenticated])
def supprimer_matiere(request, pk):
    """Supprime une liaison enseignant ↔ matière"""
    try:
        enseignant = request.user.enseignant_profile
        lien = EnseignantMatiere.objects.get(id=pk, enseignant=enseignant)
        lien.delete()
        return Response({'message': 'Matière retirée.'})
    except EnseignantMatiere.DoesNotExist:
        return Response({'error': 'Affectation non trouvée.'}, status=404)
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


# ─── Étudiants ─────────────────────────────────────────────────────────────
@api_view(['GET'])
@permission_classes([IsAuthenticated])
def students_list(request):
    niveau_nom     = request.query_params.get('niveau')
    filiere_nom    = request.query_params.get('filiere')
    specialite_nom = request.query_params.get('specialite')
    print("*****"*3)
    print("list student okay")
    if not all([niveau_nom, filiere_nom, specialite_nom]):
        return Response({'error': 'Paramètres "niveau", "filiere" et "specialite" requis.'}, status=400)

    try:
        etudiants = Etudiant.objects.filter(
            niveau__nom=niveau_nom,
            filiere__nom=filiere_nom,
            specialite__nom=specialite_nom
        )
        print("*****" * 3)
        print("studen retourner okay")
        return Response({'students': EtudiantSerializer(etudiants, many=True).data})
    except Exception as e:
        return Response({'error': str(e)}, status=500)


# ─── Notes ─────────────────────────────────────────────────────────────────
@api_view(['GET'])
@permission_classes([IsAuthenticated])
def notes_list(request):
    matiere_id = request.query_params.get('matiere_id')
    print("*****"*3)
    print("id matiere recu ", matiere_id)
    print("*****"*3)
    if not matiere_id:
        return Response({'error': 'Paramètre "matiere_id" requis.'}, status=400)

    try:
        enseignant = request.user.enseignant_profile
        print("arriver ici okay")
        notes = Note.objects.filter(matiere_id=matiere_id, enseignant=enseignant)
        return Response({'notes': NoteSerializer(notes, many=True).data})
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)


@api_view(['POST'])
@permission_classes([IsAuthenticated])
def save_notes(request):
    try:
        enseignant = request.user.enseignant_profile
        serializer = SaveNotesSerializer(
            data=request.data,
            context={'enseignant': enseignant}
        )
        if serializer.is_valid():
            result = serializer.save()
            return Response({
                'message': f'{result["saved_count"]} note(s) enregistrée(s).',
                'saved_count': result['saved_count'],
                'errors': result.get('errors', []),
            }, status=201)
        return Response(serializer.errors, status=400)
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)



@api_view(['GET'])
@permission_classes([IsAuthenticated])
def get_resultats(request):
    try:
        enseignant = request.user.enseignant_profile
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)

    niveau = request.query_params.get('niveau')
    filiere = request.query_params.get('filiere')
    specialite = request.query_params.get('specialite')
    annee = request.query_params.get('annee_academique', '2024-2025')

    if not (niveau and filiere and specialite):
        return Response(
            {'error': 'niveau, filiere et specialite sont requis.'},
            status=400
        )

    etudiants = Etudiant.objects.filter(
        niveau__nom=niveau, filiere__nom=filiere, specialite__nom=specialite
    ).order_by('nom', 'prenom')

    matieres = Matiere.objects.filter(
        niveau__nom=niveau, filiere__nom=filiere, specialites__nom=specialite
    ).distinct()

    total_credits_dispo = sum(float(m.coefficient) for m in matieres)

    resultats = []
    for etudiant in etudiants:
        notes_par_matiere = {
            n.matiere_id: n for n in Note.objects.filter(
                etudiant=etudiant, matiere__in=matieres, annee_academique=annee
            )
        }

        def calcul_semestre(get_moyenne):
            details = []
            credits_valides = 0
            for matiere in matieres:
                note = notes_par_matiere.get(matiere.id)
                moyenne = get_moyenne(note) if note else None
                valide = moyenne is not None and moyenne >= 10
                if valide:
                    credits_valides += float(matiere.coefficient)
                details.append({
                    'matiere_id': matiere.id,
                    'matiere_nom': matiere.nom,
                    'credits': float(matiere.coefficient),
                    'moyenne': moyenne,
                    'valide': valide,
                })
            return {
                'matieres': details,
                'credits_valides': credits_valides,
                'credits_requis': CREDITS_REQUIS_SEMESTRE,
                'admis': credits_valides >= CREDITS_REQUIS_SEMESTRE,
            }

        resultats.append({
            'etudiant_id': etudiant.id,
            'matricule': etudiant.matricule,
            'nom': etudiant.nom,
            'prenom': etudiant.prenom,
            's1': calcul_semestre(lambda n: n.moyenne_s1),
            's2': calcul_semestre(lambda n: n.moyenne_s2),
        })

    return Response({
        'resultats': resultats,
        'total_credits_dispo': total_credits_dispo,
        'credits_requis': CREDITS_REQUIS_SEMESTRE,
        'annee_academique': annee,
    })

@api_view(['GET'])
@permission_classes([IsAuthenticated])
def mon_planning(request):
    """Retourne l'emploi du temps de l'enseignant connecté, toutes classes confondues."""
    try:
        enseignant = request.user.enseignant_profile
    except Enseignant.DoesNotExist:
        return Response({'error': 'Profil enseignant non trouvé.'}, status=404)

    annee = request.query_params.get('annee_academique', '2024-2025')

    creneaux = EmploiDuTemps.objects.filter(
        enseignant=enseignant, annee_academique=annee
    ).select_related('niveau', 'filiere', 'specialite', 'matiere').order_by('jour', 'heure_debut')

    return Response({'creneaux': EmploiDuTempsSerializer(creneaux, many=True).data})