import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private http = inject(HttpClient);
  private csrf = '';
  private csrfRequest?: Promise<void>;

  async init(): Promise<void> {
    if (this.csrf) return;

    if (!this.csrfRequest) {
      this.csrfRequest = (async () => {
        const response = await firstValueFrom(
          this.http.get<{ ok: boolean; csrf: string }>(
            '/api?action=csrf',
            { withCredentials: true }
          )
        );

        if (!response.ok || !response.csrf) {
          throw new Error('Unable to initialize CSRF token.');
        }

        this.csrf = response.csrf;
      })().finally(() => {
        this.csrfRequest = undefined;
      });
    }

    await this.csrfRequest;
  }

  get(
    action: string,
    params: Record<string, string | number> = {}
  ) {
    const query = new URLSearchParams({
      action,
      ...Object.fromEntries(
        Object.entries(params).map(([key, value]) => [
          key,
          String(value)
        ])
      )
    });

    return firstValueFrom(
      this.http.get<any>('/api?' + query.toString(), {
        withCredentials: true
      })
    );
  }

  async post(action: string, data: any = {}) {
    await this.init();

    return firstValueFrom(
      this.http.post<any>(
        '/api?action=' + encodeURIComponent(action),
        data,
        {
          withCredentials: true,
          headers: new HttpHeaders({
            'X-CSRF-Token': this.csrf
          })
        }
      )
    );
  }
}