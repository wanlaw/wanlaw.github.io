function MakeArray(n)  {
     this.length = n
     return this
}

dayNames = new MakeArray(7)
Goodman  = new Date()

dayNames[1] = "일"
dayNames[2] = "월"
dayNames[3] = "화"
dayNames[4] = "수"
dayNames[5] = "목"
dayNames[6] = "금"
dayNames[7] = "토"

month   = Goodman.getMonth() + 1
day     = Goodman.getDate()
year    = Goodman.getYear()
theDay  = dayNames[Goodman.getDay() + 1]

document.write(month + "월 " + day + "일 (" + theDay + ")");								