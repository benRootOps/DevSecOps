from rest_framework.decorators import api_view, permission_classes
from rest_framework.response import Response
from rest_framework.permissions import IsAuthenticated
from rest_framework import status
from django.utils import timezone
from django.utils.dateparse import parse_datetime
from ..models import Enseignant, Administrateur, CustomUser, PeriodeSaisie


# ─── helper ───────────────────────────────────────────────────────────────────
def _check_admin(request):
    """Retourne (admin, None) ou (None, Response d'erreur)"""
    try:
        admin = request.user.admin_profile
        if not admin.is_approved:
            return None, Response(
                {'error': 'Accès non autorisé.'},
                status=status.HTTP_403_FORBIDDEN
            )
        return admin, None
    except Administrateur.DoesNotExist:
        return None, Response(
            {'error': 'Profil administrateur non trouvé.'},
            status=status.HTTP_404_NOT_FOUND
        )


# ─── Profil admin ──────────────────────────────────────────────────────────────
@api_view(['GET'])
@permission_classes([IsAuthenticated])
def admin_profile(request):
    try:
        admin = request.user.admin_profile
        return Response({
            'id': admin.id,
            'nom': admin.nom,
            'prenom': admin.prenom,
            'email': request.user.email,
            'is_approved': admin.is_approved
        })
    except Administrateur.DoesNotExist:
        return Response({'error': 'Profil administrateur non trouvé.'}, status=404)


# ─── Enseignants ───────────────────────────────────────────────────────────────
@api_view(['GET'])
@permission_classes([IsAuthenticated])
def get_enseignants(request):
    admin, err = _check_admin(request)
    if err:
        return err

    enseignants = Enseignant.objects.all().select_related('user')
    data = [{
        'id': e.id,
        'nom': e.nom,
        'prenom': e.prenom,
        'date_naissance': e.date_naissance,
        'lieu_naissance': e.lieu_naissance,
        'is_verified': e.is_verified,
        'is_active': e.is_active,
        'created_at': e.created_at,
        'updated_at': e.updated_at,
        'user': {'email': e.user.email, 'username': e.user.username}
    } for e in enseignants]

    return Response({'enseignants': data})


@api_view(['POST'])
@permission_classes([IsAuthenticated])
def validate_enseignant(request, enseignant_id):
    admin, err = _check_admin(request)
    if err:
        return err

    try:
        enseignant = Enseignant.objects.get(id=enseignant_id)
        enseignant.is_active = request.data.get('approved', False)
        enseignant.save()
        msg = 'Enseignant validé.' if enseignant.is_active else 'Enseignant rejeté.'
        return Response({'message': msg})
    except Enseignant.DoesNotExist:
        return Response({'error': 'Enseignant non trouvé.'}, status=404)


# ─── Administrateurs ───────────────────────────────────────────────────────────
@api_view(['GET'])
@permission_classes([IsAuthenticated])
def get_administrateurs(request):
    admin, err = _check_admin(request)
    if err:
        return err

    admins = Administrateur.objects.all().select_related('user')
    data = [{
        'id': a.id,
        'nom': a.nom,
        'prenom': a.prenom,
        'date_naissance': a.date_naissance,
        'lieu_naissance': a.lieu_naissance,
        'is_approved': a.is_approved,
        'created_at': a.created_at,
        'updated_at': a.updated_at,
        'user': {'email': a.user.email, 'username': a.user.username}
    } for a in admins]

    return Response({'administrateurs': data})


@api_view(['POST'])
@permission_classes([IsAuthenticated])
def approve_administrateur(request, admin_id):
    admin, err = _check_admin(request)
    if err:
        return err

    try:
        target = Administrateur.objects.get(id=admin_id)
        target.is_approved = request.data.get('approved', False)
        target.save()
        msg = 'Administrateur approuvé.' if target.is_approved else 'Administrateur rejeté.'
        return Response({'message': msg})
    except Administrateur.DoesNotExist:
        return Response({'error': 'Administrateur non trouvé.'}, status=404)


# ══════════════════════════════════════════════════════════════════════════════
# GESTION DES PÉRIODES DE SAISIE
# ══════════════════════════════════════════════════════════════════════════════

@api_view(['GET'])
@permission_classes([IsAuthenticated])
def get_periodes(request):
    """
    Liste toutes les périodes de l'année académique.
    Query param optionnel : ?annee=2024-2025
    """
    admin, err = _check_admin(request)
    if err:
        return err

    annee = request.query_params.get('annee', '2024-2025')
    periodes = PeriodeSaisie.objects.filter(
        annee_academique=annee
    ).select_related('ouvert_par').order_by('date_debut')

    data = [_format_periode(p) for p in periodes]

    # Sections actuellement ouvertes (utile pour le frontend)
    sections_ouvertes = PeriodeSaisie.sections_ouvertes(annee)

    return Response({
        'periodes': data,
        'sections_ouvertes': sections_ouvertes,
        'annee_academique': annee,
    })


@api_view(['POST'])
@permission_classes([IsAuthenticated])
def creer_periode(request):
    """
    Crée une nouvelle période de saisie.

    Body JSON :
    {
        "section": "CC1" | "SN1" | "CC2" | "SN2" | "TOUT",
        "date_debut": "2025-01-15T08:00:00",
        "date_fin":   "2025-01-30T23:59:59",
        "annee_academique": "2024-2025"   // optionnel
    }
    """
    admin, err = _check_admin(request)
    if err:
        return err

    section = request.data.get('section', '').upper()
    date_debut_raw = request.data.get('date_debut')
    date_fin_raw = request.data.get('date_fin')
    annee = request.data.get('annee_academique', '2024-2025')

    # Validation des champs requis
    if not section or not date_debut_raw or not date_fin_raw:
        return Response(
            {'error': 'section, date_debut et date_fin sont requis.'},
            status=status.HTTP_400_BAD_REQUEST
        )

    # Conversion des dates (string ISO -> datetime)
    date_debut = parse_datetime(date_debut_raw)
    date_fin = parse_datetime(date_fin_raw)

    if date_debut is None or date_fin is None:
        return Response(
            {'error': 'Format de date invalide. Utilisez le format ISO 8601 (ex: 2025-01-15T08:00:00).'},
            status=status.HTTP_400_BAD_REQUEST
        )

    # Si USE_TZ=True, s'assurer que les dates sont "aware"
    if timezone.is_naive(date_debut):
        date_debut = timezone.make_aware(date_debut)
    if timezone.is_naive(date_fin):
        date_fin = timezone.make_aware(date_fin)

    if date_fin <= date_debut:
        return Response(
            {'error': 'date_fin doit être postérieure à date_debut.'},
            status=status.HTTP_400_BAD_REQUEST
        )

    # Vérification des règles métier
    peut, message = PeriodeSaisie.peut_activer_section(section, annee)
    if not peut:
        return Response({'error': message}, status=status.HTTP_400_BAD_REQUEST)

    # Une seule période active par section à la fois
    doublon = PeriodeSaisie.objects.filter(
        annee_academique=annee,
        section=section,
        is_active=True
    ).exists()
    if doublon:
        return Response(
            {'error': f'Une période {section} est déjà active. Désactivez-la d\'abord.'},
            status=status.HTTP_400_BAD_REQUEST
        )

    periode = PeriodeSaisie.objects.create(
        section=section,
        date_debut=date_debut,
        date_fin=date_fin,
        annee_academique=annee,
        is_active=True,
        ouvert_par=admin,
    )

    return Response({
        'message': f'Période {section} créée avec succès.',
        'periode': _format_periode(periode)
    }, status=status.HTTP_201_CREATED)


@api_view(['PATCH'])
@permission_classes([IsAuthenticated])
def toggle_periode(request, periode_id):
    """
    Active ou désactive une période existante.

    Body JSON : { "is_active": true | false }
    """
    admin, err = _check_admin(request)
    if err:
        return err

    try:
        periode = PeriodeSaisie.objects.get(id=periode_id)
    except PeriodeSaisie.DoesNotExist:
        return Response({'error': 'Période non trouvée.'}, status=404)

    is_active = request.data.get('is_active')
    if is_active is None:
        return Response({'error': 'is_active est requis.'}, status=400)

    # Si on veut activer, vérifier les règles métier
    if is_active:
        peut, message = PeriodeSaisie.peut_activer_section(
            periode.section, periode.annee_academique
        )
        if not peut:
            return Response({'error': message}, status=400)

    periode.is_active = is_active
    periode.save()

    statut = 'activée' if is_active else 'désactivée'
    return Response({
        'message': f'Période {periode.section} {statut}.',
        'periode': _format_periode(periode)
    })


@api_view(['DELETE'])
@permission_classes([IsAuthenticated])
def supprimer_periode(request, periode_id):
    """Supprime une période (uniquement si inactive)"""
    admin, err = _check_admin(request)
    if err:
        return err

    try:
        periode = PeriodeSaisie.objects.get(id=periode_id)
    except PeriodeSaisie.DoesNotExist:
        return Response({'error': 'Période non trouvée.'}, status=404)

    if periode.is_active:
        return Response(
            {'error': 'Impossible de supprimer une période active. Désactivez-la d\'abord.'},
            status=400
        )

    periode.delete()
    return Response({'message': 'Période supprimée.'})


@api_view(['POST'])
@permission_classes([IsAuthenticated])
def tout_activer(request):
    """
    Mode rattrapage : active toutes les sections.
    Crée une période TOUT ou active la dernière existante.
    Body JSON : { "annee_academique": "2024-2025" }  // optionnel
    """
    admin, err = _check_admin(request)
    if err:
        return err

    annee = request.data.get('annee_academique', '2024-2025')
    now = timezone.now()

    # Cherche une période TOUT existante non active, sinon en crée une
    periode, created = PeriodeSaisie.objects.get_or_create(
        annee_academique=annee,
        section='TOUT',
        defaults={
            'date_debut': now,
            'date_fin': now.replace(year=now.year + 1),
            'is_active': True,
            'ouvert_par': admin,
        }
    )

    if not created:
        periode.is_active = True
        periode.date_debut = now
        periode.save()

    return Response({
        'message': 'Toutes les sections sont maintenant ouvertes (mode rattrapage).',
        'sections_ouvertes': ['CC1', 'SN1', 'CC2', 'SN2'],
        'periode': _format_periode(periode)
    })


@api_view(['GET'])
@permission_classes([IsAuthenticated])
def sections_ouvertes(request):
    """
    Endpoint léger appelé par le frontend enseignant pour savoir
    quelles colonnes sont éditables.
    Query param optionnel : ?annee=2024-2025
    """
    annee = request.query_params.get('annee', '2024-2025')
    sections = PeriodeSaisie.sections_ouvertes(annee)
    return Response({
        'sections_ouvertes': sections,
        'annee_academique': annee,
    })


# ─── helper interne ────────────────────────────────────────────────────────────
def _format_periode(p):
    return {
        'id': p.id,
        'section': p.section,
        'section_label': p.get_section_display(),
        'date_debut': p.date_debut,
        'date_fin': p.date_fin,
        'annee_academique': p.annee_academique,
        'is_active': p.is_active,
        'est_en_cours': p.est_en_cours,
        'ouvert_par': (
            f"{p.ouvert_par.prenom} {p.ouvert_par.nom}"
            if p.ouvert_par else None
        ),
    }

from ..models import EmploiDuTemps
from ..serializers import EmploiDuTempsSerializer

@api_view(['GET'])
@permission_classes([IsAuthenticated])
def get_emplois_du_temps(request):
    admin, err = _check_admin(request)
    if err:
        return err

    annee = request.query_params.get('annee_academique', '2024-2025')
    niveau = request.query_params.get('niveau')
    filiere = request.query_params.get('filiere')
    specialite = request.query_params.get('specialite')

    creneaux = EmploiDuTemps.objects.filter(annee_academique=annee).select_related(
        'niveau', 'filiere', 'specialite', 'matiere', 'enseignant'
    )
    if niveau:
        creneaux = creneaux.filter(niveau__nom=niveau)
    if filiere:
        creneaux = creneaux.filter(filiere__nom=filiere)
    if specialite:
        creneaux = creneaux.filter(specialite__nom=specialite)

    return Response({'creneaux': EmploiDuTempsSerializer(creneaux, many=True).data})


@api_view(['POST'])
@permission_classes([IsAuthenticated])
def creer_creneau(request):
    admin, err = _check_admin(request)
    if err:
        return err

    serializer = EmploiDuTempsSerializer(data=request.data)
    if serializer.is_valid():
        # Vérifier les conflits d'horaire pour cet enseignant ce jour-là
        enseignant_id = request.data.get('enseignant')
        jour = request.data.get('jour')
        heure_debut = request.data.get('heure_debut')
        heure_fin = request.data.get('heure_fin')
        annee = request.data.get('annee_academique', '2024-2025')

        conflit = EmploiDuTemps.objects.filter(
            enseignant_id=enseignant_id, jour=jour, annee_academique=annee,
            heure_debut__lt=heure_fin, heure_fin__gt=heure_debut,
        ).exists()
        if conflit:
            return Response(
                {'error': "Cet enseignant a déjà un cours sur ce créneau."},
                status=400
            )

        creneau = serializer.save()
        return Response(EmploiDuTempsSerializer(creneau).data, status=201)
    return Response(serializer.errors, status=400)


@api_view(['DELETE'])
@permission_classes([IsAuthenticated])
def supprimer_creneau(request, creneau_id):
    admin, err = _check_admin(request)
    if err:
        return err
    try:
        creneau = EmploiDuTemps.objects.get(id=creneau_id)
        creneau.delete()
        return Response({'message': 'Créneau supprimé.'})
    except EmploiDuTemps.DoesNotExist:
        return Response({'error': 'Créneau non trouvé.'}, status=404)
