import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from './api.service';
@Component({selector:'app-root',standalone:true,imports:[CommonModule,FormsModule],templateUrl:'./app.component.html'})
export class AppComponent implements OnInit {
 api=inject(ApiService);page='login';role='';user:any=null;error='';notice='';busy=false;
 login={identity:'',password:''};adminLogin={username:'',password:''};register={name:'',phone:'',email:'',username:'',password:'',address:''};otp='';pendingId=0;
 destinations:any[]=[];attractions:any[]=[];hotels:any[]=[];vehicles:any[]=[];selected:any=null;selectedAttractions:number[]=[];form:any={start_date:'',end_date:'',people:1,hotel_id:0,vehicle_id:0,helmet:false};quote:any=null;itinerary:any[]=[];trips:any[]=[];invoice:any=null;stats:any=null;adminTable='vehicles';adminRows:any[]=[];edit:any={};
 async ngOnInit(){try{await this.api.init();const r=await this.api.get('me');this.user=r.user;this.role=r.role;this.page=r.role==='admin'?'admin':'home';await this.loadHome();}catch{} }
 async call(fn:()=>Promise<any>){this.error='';this.notice='';this.busy=true;try{await fn();}catch(e:any){this.error=e?.error?.message||e?.message||'Request failed';}finally{this.busy=false;}}
 async signIn(admin=false){await this.call(async()=>{const r=await this.api.post(admin?'admin-login':'login',admin?this.adminLogin:this.login);this.user=r.user;this.role=admin?'admin':'user';this.page=admin?'admin':'home';await this.loadHome();});}
 async signUp(){await this.call(async()=>{const r=await this.api.post('register',this.register);this.pendingId=r.user_id;this.notice=r.dev_otp?'Development OTP: '+r.dev_otp:r.message;this.page='otp';});}
 async verify(){await this.call(async()=>{const r=await this.api.post('verify-otp',{user_id:this.pendingId,otp:this.otp});this.notice=r.message;this.page='login';});}
 async logout(){await this.call(async()=>{await this.api.post('logout');this.role='';this.user=null;this.page='login';});}
 async loadHome(){if(this.role==='admin'){await this.loadStats();await this.loadAdmin('vehicles');}else{this.destinations=(await this.api.get('destinations')).items;}}
 async openDestination(d:any){await this.call(async()=>{this.selected=d;this.selectedAttractions=[];this.attractions=(await this.api.get('attractions',{destination_id:d.id})).items;this.hotels=(await this.api.get('hotels',{destination_id:d.id})).items;this.vehicles=(await this.api.get('vehicles',{destination_id:d.id})).items;this.quote=null;this.itinerary=[];this.page='destination';});}
 toggle(id:number){this.selectedAttractions=this.selectedAttractions.includes(id)?this.selectedAttractions.filter(x=>x!==id):[...this.selectedAttractions,id];}
 get payload(){return {...this.form,destination_id:this.selected.id,attraction_ids:this.selectedAttractions,hotel_id:Number(this.form.hotel_id),vehicle_id:Number(this.form.vehicle_id),people:Number(this.form.people)};}
 async preview(){await this.call(async()=>{this.quote=await this.api.post('quote',this.payload);this.itinerary=(await this.api.post('itinerary',{destination_id:this.selected.id,attraction_ids:this.selectedAttractions,days:this.quote.days})).days;this.page='summary';});}
 async book(){await this.call(async()=>{const r=await this.api.post('book',this.payload);this.notice='Trip booked. Reference #'+r.trip_id+'. Payment is pending.';this.quote=r;this.page='payment';});}
 async demoPay(){await this.call(async()=>{await this.api.post('demo-pay',{trip_id:this.quote.trip_id});this.notice='Demo payment recorded. No real charge.';await this.showInvoice(this.quote.trip_id);});}
 async loadTrips(){await this.call(async()=>{this.trips=(await this.api.get('my-trips')).items;this.page='trips';});}
 async showInvoice(id:number){await this.call(async()=>{this.invoice=(await this.api.get('invoice',{id})).invoice;this.page='invoice';});}
 async loadStats(){this.stats=await this.api.get('admin-stats');}
 async loadAdmin(table:string){await this.call(async()=>{this.adminTable=table;this.adminRows=(await this.api.get('admin-list',{table})).items;this.edit={};this.page='admin';});}
 async saveAdmin(){await this.call(async()=>{await this.api.post('admin-save',{table:this.adminTable,...this.edit});await this.loadAdmin(this.adminTable);this.notice='Saved successfully';});}
 async deleteAdmin(row:any){if(!confirm('Delete/deactivate this item?'))return;await this.call(async()=>{await this.api.post('admin-delete',{table:this.adminTable,id:row.id});await this.loadAdmin(this.adminTable);});}
 get editableFields(){const map:any={destinations:['name','description','image_url'],attractions:['destination_id','name','description','image_url','entry_price','duration_minutes'],hotels:['destination_id','name','description','image_url','rating','price_per_night','rooms_total','active'],vehicles:['destination_id','name','vehicle_type','registration_number','image_url','price_per_day','active']};return map[this.adminTable]||[];}
 print(){window.print();}
 get keys(){return this.adminRows.length?Object.keys(this.adminRows[0]).filter(k=>k!=='password_hash'):[];}
}
