"""
ViewSets pour les opérations CRUD
"""

from rest_framework import viewsets
from rest_framework.permissions import IsAuthenticated

from ..models import Niveau, Filiere, Specialite, Matiere, Etudiant, Note
from ..serializers import (
    NiveauSerializer, FiliereSerializer, SpecialiteSerializer,
    MatiereSerializer, EtudiantSerializer, NoteSerializer
)


class NiveauViewSet(viewsets.ModelViewSet):
    queryset = Niveau.objects.all().order_by('ordre', 'nom')
    serializer_class = NiveauSerializer
    permission_classes = [IsAuthenticated]


class FiliereViewSet(viewsets.ModelViewSet):
    queryset = Filiere.objects.all().order_by('nom')
    serializer_class = FiliereSerializer
    permission_classes = [IsAuthenticated]


class SpecialiteViewSet(viewsets.ModelViewSet):
    queryset = Specialite.objects.all()
    serializer_class = SpecialiteSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        queryset = super().get_queryset()
        # Filtre par filiere_id — utilisé par les selects enchaînés du frontend
        filiere = self.request.query_params.get('filiere')
        if filiere:
            queryset = queryset.filter(filiere_id=filiere)
        return queryset.order_by('nom')


class MatiereViewSet(viewsets.ModelViewSet):
    queryset = Matiere.objects.all()
    serializer_class = MatiereSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        queryset = super().get_queryset()
        # Filtres optionnels — utilisés par les selects enchaînés
        niveau  = self.request.query_params.get('niveau')
        filiere = self.request.query_params.get('filiere')
        specialite = self.request.query_params.get('specialite')

        if niveau:
            queryset = queryset.filter(niveau__nom=niveau)
        if filiere:
            queryset = queryset.filter(filiere__nom=filiere)
        if specialite:
            queryset = queryset.filter(specialites__nom=specialite)

        return queryset.distinct().order_by('nom')


class EtudiantViewSet(viewsets.ModelViewSet):
    queryset = Etudiant.objects.all()
    serializer_class = EtudiantSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        queryset = super().get_queryset()
        niveau     = self.request.query_params.get('niveau')
        filiere    = self.request.query_params.get('filiere')
        specialite = self.request.query_params.get('specialite')

        if niveau:
            queryset = queryset.filter(niveau__nom=niveau)
        if filiere:
            queryset = queryset.filter(filiere__nom=filiere)
        if specialite:
            queryset = queryset.filter(specialite__nom=specialite)

        return queryset.order_by('nom', 'prenom')


class NoteViewSet(viewsets.ModelViewSet):
    queryset = Note.objects.all()
    serializer_class = NoteSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        queryset = super().get_queryset()

        if hasattr(self.request.user, 'enseignant_profile'):
            queryset = queryset.filter(enseignant=self.request.user.enseignant_profile)

        etudiant_id = self.request.query_params.get('etudiant_id')
        matiere_id  = self.request.query_params.get('matiere_id')

        if etudiant_id:
            queryset = queryset.filter(etudiant_id=etudiant_id)
        if matiere_id:
            queryset = queryset.filter(matiere_id=matiere_id)

        return queryset
