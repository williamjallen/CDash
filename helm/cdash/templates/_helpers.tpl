{{- define "cdash.environment" }}
          env:
            - name: "DB_PASSWORD"
              value: {{ .Values.global.postgresql.auth.postgresPassword | quote }}
            - name: "APP_KEY"
              valueFrom:
                secretKeyRef:
                  name: "{{ .Release.Name }}-website"
                  key: "APP_KEY"
          envFrom:
            - configMapRef:
                name: "{{ .Release.Name }}-website"
{{- end }}
